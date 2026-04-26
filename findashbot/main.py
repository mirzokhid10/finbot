# main.py

import os
import asyncio
import logging
from dotenv import load_dotenv
from aiogram import Bot, Dispatcher, types, F
from aiogram.filters import Command
from aiogram.types import ReplyKeyboardMarkup, KeyboardButton, CallbackQuery
from user.registration import register_user_if_not_exists
from db.models import User, Category, Transaction
from db.database import SessionLocal
from aiogram.fsm.context import FSMContext
from datetime import datetime
from transaction.transaction import (
    handle_voice_command,
    handle_missing_info_logic,
    handle_custom_category_name,
    handle_custom_category_type,
    TransactionFlow,
    start_correction,
    process_deletion
)
from category.category import (
    list_categories,
    start_add_category,
    receive_category_name,
    receive_category_type,
    CategoryFlow
)

load_dotenv()
bot = Bot(token=os.getenv('BOT_TOKEN'))
dp = Dispatcher()
logger = logging.getLogger(__name__)


def get_main_menu():
    """Main menu keyboard"""
    kb = [
        [KeyboardButton(text="➕ Add Transaction")],
        [KeyboardButton(text="📊 Summary"), KeyboardButton(text="🗑 Delete Last")],
        [KeyboardButton(text="📂 Categories")]
    ]
    return ReplyKeyboardMarkup(keyboard=kb, resize_keyboard=True)


# ==================== CALLBACK QUERY HANDLERS ====================

@dp.callback_query(F.data.startswith("edit_tx:"))
async def handle_edit_transaction(callback: CallbackQuery, state: FSMContext):
    """Handle edit transaction button"""
    tx_id = int(callback.data.split(":")[1])

    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(callback.from_user.id)).first()
        await start_correction(callback.message, user, state)

    await callback.answer()


@dp.callback_query(F.data.startswith("delete_tx:"))
async def handle_delete_transaction(callback: CallbackQuery):
    """Handle delete transaction button"""
    tx_id = int(callback.data.split(":")[1])

    with SessionLocal() as db:
        tx = db.query(Transaction).filter(Transaction.id == tx_id).first()
        if tx:
            amount = tx.amount
            tx_type = tx.type
            cat_name = tx.category.name if tx.category else "Uncategorized"

            db.delete(tx)
            db.commit()

            await callback.message.edit_text(
                f"🗑️ **Transaction Deleted**\n\n"
                f"Removed: {amount:,.0f} som {tx_type}\n"
                f"Category: {cat_name}",
                parse_mode="Markdown"
            )
        else:
            await callback.answer("❌ Transaction not found", show_alert=True)

    await callback.answer()


@dp.callback_query(F.data.startswith("create_cat:"))
async def handle_create_custom_category_callback(callback: CallbackQuery, state: FSMContext):
    """Handle create custom category from inline button"""
    category_name = callback.data.split(":", 1)[1]

    data = await state.get_data()
    pending = data.get('pending_transaction')

    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(callback.from_user.id)).first()

        cat_type = pending.get('type', 'expense')
        new_cat = Category(
            user_id=user.id,
            name=category_name.title(),
            type=cat_type,
            is_custom=True
        )
        db.add(new_cat)
        db.commit()

        await callback.message.edit_text(f"✅ Created category: **{category_name.title()}**", parse_mode="Markdown")

        pending['category'] = category_name.title()
        from transaction.transaction import save_transaction_with_confirmation
        await save_transaction_with_confirmation(callback.message, pending, user)
        await state.clear()

    await callback.answer()


@dp.callback_query(F.data == "choose_existing")
async def handle_choose_existing_category(callback: CallbackQuery, state: FSMContext):
    """Show existing categories when user declines custom creation"""
    data = await state.get_data()
    pending = data.get('pending_transaction')

    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(callback.from_user.id)).first()

        from transaction.transaction import ask_for_category_selection
        await ask_for_category_selection(callback.message, pending, user, state)

    await callback.message.delete()
    await callback.answer()


@dp.callback_query(F.data == "use_others")
async def handle_use_others_category(callback: CallbackQuery, state: FSMContext):
    """Use 'Others' category"""
    data = await state.get_data()
    pending = data.get('pending_transaction')

    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(callback.from_user.id)).first()

        from transaction.transaction import save_transaction_with_confirmation
        await save_transaction_with_confirmation(callback.message, pending, user, use_others=True)
        await state.clear()

    await callback.message.delete()
    await callback.answer()


@dp.callback_query(F.data == "cancel_edit")
async def handle_cancel_edit(callback: CallbackQuery):
    """Cancel edit operation"""
    await callback.message.edit_text("❌ Edit cancelled")
    await callback.answer()


# ==================== MESSAGE HANDLERS ====================

# 1. Commands
@dp.message(Command("start"))
async def cmd_start(message: types.Message):
    """Handle /start command"""
    user_data = message.from_user.model_dump()
    msg, is_new = await register_user_if_not_exists(user_data)

    db = SessionLocal()
    try:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()
        needs_phone = user and not user.phone
    finally:
        db.close()

    if needs_phone:
        kb = ReplyKeyboardMarkup(
            keyboard=[[KeyboardButton(text="📱 Share Phone Number", request_contact=True)]],
            resize_keyboard=True, one_time_keyboard=True
        )
        await message.answer(
            f"👋 Welcome to Business Finance Manager!\n\n"
            f"📲 Please share your phone number to complete registration:",
            reply_markup=kb
        )
    else:
        welcome_text = (
            f"👋 Welcome back, {message.from_user.first_name}!\n\n"
            f"💰 **Quick Guide:**\n"
            f"• Tap buttons below for quick actions\n"
            f"• Send voice messages to log transactions\n"
            f"• Ask questions like 'How much did I spend this week?'\n\n"
            f"What would you like to do?"
        )
        await message.answer(welcome_text, reply_markup=get_main_menu(), parse_mode="Markdown")


@dp.message(Command("categories"))
async def cmd_categories(message: types.Message):
    """Handle /categories command"""
    await list_categories(message)


@dp.message(Command("addcategory"))
async def cmd_add_category(message: types.Message, state: FSMContext):
    """Handle /addcategory command"""
    await start_add_category(message, state)


# 2. Contact
@dp.message(F.contact)
async def handle_contact(message: types.Message):
    """Handle phone number sharing"""
    telegram_id = str(message.from_user.id)
    phone_number = message.contact.phone_number

    db = SessionLocal()
    try:
        user = db.query(User).filter(User.telegram_id == telegram_id).first()
        if user:
            user.phone = phone_number
            user.updated_at = datetime.now()
            db.commit()

            welcome_text = (
                f"✅ Registration complete!\n\n"
                f"💰 **You can now:**\n"
                f"• Tap '➕ Add Transaction' to start logging\n"
                f"• Send voice messages anytime\n"
                f"• Ask me for summaries and reports\n\n"
                f"Ready to get started? 🚀"
            )
            await message.answer(welcome_text, reply_markup=get_main_menu(), parse_mode="Markdown")
        else:
            await message.answer("❌ Please type /start first.")
    finally:
        db.close()


# 3. Voice
@dp.message(F.voice)
async def voice_handler(message: types.Message, state: FSMContext):
    """Handle voice messages"""
    await handle_voice_command(message, bot, state)


# 4. FSM State Handlers
@dp.message(TransactionFlow.waiting_for_custom_category_name)
async def custom_category_name_handler(message: types.Message, state: FSMContext):
    """Handle custom category name input"""
    await handle_custom_category_name(message, state)


@dp.message(TransactionFlow.waiting_for_custom_category_type)
async def custom_category_type_handler(message: types.Message, state: FSMContext):
    """Handle custom category type selection"""
    await handle_custom_category_type(message, state)


@dp.message(TransactionFlow.waiting_for_missing_info)
async def missing_info_handler(message: types.Message, state: FSMContext):
    """Handle missing information input"""
    await handle_missing_info_logic(message, state)


@dp.message(CategoryFlow.waiting_for_category_name)
async def category_name_handler(message: types.Message, state: FSMContext):
    """Handle category name input from /addcategory"""
    await receive_category_name(message, state)


@dp.message(CategoryFlow.waiting_for_category_type)
async def category_type_handler(message: types.Message, state: FSMContext):
    """Handle category type selection from /addcategory"""
    await receive_category_type(message, state)


# 5. Button Handlers
@dp.message(F.text == "➕ Add Transaction")
async def add_transaction_btn(message: types.Message):
    """Handle Add Transaction button"""
    await message.answer(
        "🎤 **Ready to record.**\n\n"
        "Please send a **voice message** describing the transaction.\n"
        "Example: _'Spent 50,000 on logistics today'_",
        parse_mode="Markdown"
    )


@dp.message(F.text == "📊 Summary")
async def summary_btn(message: types.Message):
    """Handle Summary button"""
    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()
        if not user:
            await message.answer("Please /start first!")
            return

        fake_data = {
            'intent': 'query',
            'query_target': 'show me summary for this month'
        }

        from transaction.transaction import process_query
        await process_query(message, fake_data, user)


@dp.message(F.text == "🗑 Delete Last")
async def delete_btn_handler(message: types.Message):
    """Handle Delete Last button"""
    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()
        await process_deletion(message, user)


@dp.message(F.text == "📂 Categories")
async def categories_btn_handler(message: types.Message):
    """Handle Categories button"""
    await list_categories(message)


# 6. Text Fallback (MUST BE LAST)
@dp.message(F.text)
async def text_handler(message: types.Message, state: FSMContext):
    """Handle any text message as transaction input"""

    # Skip button texts (already handled above)
    if message.text in ["➕ Add Transaction", "📊 Summary", "🗑 Delete Last", "📂 Categories"]:
        return

    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()
        if not user:
            await message.answer("Please /start first!")
            return

        categories = db.query(Category).filter(Category.user_id == user.id).all()
        cat_names = [c.name for c in categories]

    from services.ai_service import extract_intent
    data = await extract_intent(message.text, cat_names)
    data['available_categories'] = cat_names

    intent = data.get('intent')
    if intent == "log_transaction":
        from transaction.transaction import process_logging
        await process_logging(message, data, user, cat_names, state)
    elif intent == "query":
        from transaction.transaction import process_query
        await process_query(message, data, user)
    elif intent == "delete_last":
        await process_deletion(message, user)
    elif intent == "correct_last":
        await start_correction(message, user, state)
    else:
        await message.answer(
            f"💭 I understood: '{message.text}'\n\n"
            f"Try:\n"
            f"• 'Received 500,000 from client'\n"
            f"• 'Spent 50,000 on logistics'\n"
            f"• 'How much did I earn this week?'"
        )


# ==================== MAIN ====================

async def main():
    """Start the bot"""
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    logger.info("🚀 Bot is starting...")
    try:
        await dp.start_polling(bot)
    finally:
        await bot.session.close()


if __name__ == "__main__":
    asyncio.run(main())