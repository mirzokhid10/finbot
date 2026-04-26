# category/category.py

import logging
from aiogram import types
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from sqlalchemy import func

from db.database import SessionLocal
from db.models import User, Category

logger = logging.getLogger(__name__)


class CategoryFlow(StatesGroup):
    waiting_for_category_name = State()
    waiting_for_category_type = State()


async def list_categories(message: types.Message):
    """Show all categories for the user"""
    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()
        if not user:
            await message.answer("Please /start first!")
            return

        income_cats = db.query(Category).filter(
            Category.user_id == user.id,
            Category.type == "income"
        ).all()

        expense_cats = db.query(Category).filter(
            Category.user_id == user.id,
            Category.type == "expense"
        ).all()

        msg = "📂 **Your Categories**\n\n"

        msg += "💰 **Income:**\n"
        for cat in income_cats:
            custom_badge = "🔧" if cat.is_custom else ""
            msg += f"  • {cat.name} {custom_badge}\n"

        msg += "\n💸 **Expense:**\n"
        for cat in expense_cats:
            custom_badge = "🔧" if cat.is_custom else ""
            msg += f"  • {cat.name} {custom_badge}\n"

        msg += "\n_🔧 = Custom category_"

        await message.answer(msg, parse_mode="Markdown")


async def start_add_category(message: types.Message, state: FSMContext):
    """Start flow to add a custom category"""
    await state.set_state(CategoryFlow.waiting_for_category_name)
    await message.answer(
        "🆕 **Create Custom Category**\n\n"
        "What should we call this category?\n"
        "_Example: Equipment, Travel, Consulting_",
        parse_mode="Markdown"
    )


async def receive_category_name(message: types.Message, state: FSMContext):
    """Receive category name and ask for type"""
    category_name = message.text.strip()

    # Check if category already exists
    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()
        existing = db.query(Category).filter(
            Category.user_id == user.id,
            func.lower(Category.name) == func.lower(category_name)
        ).first()

        if existing:
            await message.answer(f"❌ Category '{category_name}' already exists!")
            await state.clear()
            return

    # Store name and ask for type
    await state.update_data(category_name=category_name)
    await state.set_state(CategoryFlow.waiting_for_category_type)

    from aiogram.types import ReplyKeyboardMarkup, KeyboardButton
    kb = ReplyKeyboardMarkup(
        keyboard=[
            [KeyboardButton(text="💰 Income"), KeyboardButton(text="💸 Expense")]
        ],
        resize_keyboard=True,
        one_time_keyboard=True
    )

    await message.answer(
        f"📝 Category name: **{category_name}**\n\n"
        f"Is this an Income or Expense category?",
        reply_markup=kb,
        parse_mode="Markdown"
    )


async def receive_category_type(message: types.Message, state: FSMContext):
    """Save the new category"""
    # Parse type
    text = message.text.lower()
    if "income" in text:
        cat_type = "income"
    elif "expense" in text:
        cat_type = "expense"
    else:
        await message.answer("❌ Please choose 'Income' or 'Expense'")
        return

    # Get stored name
    data = await state.get_data()
    category_name = data.get('category_name')

    if not category_name:
        await message.answer("❌ Something went wrong. Please start over with /addcategory")
        await state.clear()
        return

    # Save to database
    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()

        new_category = Category(
            user_id=user.id,
            name=category_name,
            type=cat_type,
            is_custom=True
        )
        db.add(new_category)
        db.commit()
        db.refresh(new_category)

    # Show main menu again
    from main import get_main_menu
    await message.answer(
        f"✅ Category **{category_name}** ({cat_type}) created!",
        reply_markup=get_main_menu(),
        parse_mode="Markdown"
    )
    await state.clear()


async def delete_category(message: types.Message):
    """Delete a custom category (only custom ones)"""
    await message.answer(
        "🗑️ **Delete Category**\n\n"
        "Send me the exact name of the custom category you want to delete.\n"
        "_Note: You can only delete custom categories (🔧)_",
        parse_mode="Markdown"
    )
    # You can implement FSM flow for deletion similar to add