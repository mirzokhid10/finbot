# transaction/transaction.py

import os
import logging
import re
from datetime import datetime, timedelta
from sqlalchemy import func
from aiogram import Bot, types
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import ReplyKeyboardMarkup, KeyboardButton, InlineKeyboardMarkup, InlineKeyboardButton

from db.database import SessionLocal
from db.models import User, Category, Transaction
from services.ai_service import (
    transcribe_voice,
    extract_intent,
    generate_confirmation_message,
    generate_clarification_question
)

logger = logging.getLogger(__name__)

# Memory to store last transaction for each user
user_mem = {}


class TransactionFlow(StatesGroup):
    waiting_for_missing_info = State()
    waiting_for_correction = State()
    waiting_for_custom_category_name = State()
    waiting_for_custom_category_type = State()


async def handle_voice_command(message: types.Message, bot: Bot, state: FSMContext):
    """Entry point for voice messages"""

    processing_msg = await message.answer("🎧 Processing your voice message...")

    try:
        # 1. Download & Transcribe
        file_id = message.voice.file_id
        file = await bot.get_file(file_id)
        file_path = f"voice_{file_id}.ogg"
        await bot.download_file(file.file_path, file_path)

        text = await transcribe_voice(file_path)
        os.remove(file_path)

        # Delete processing message
        await processing_msg.delete()

        # Show what was heard
        await message.answer(f"_I heard: \"{text}\"_", parse_mode="Markdown")

        # 2. Get DB Context
        with SessionLocal() as db:
            user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()
            if not user:
                await message.answer("Please register first by typing /start")
                return

            categories = db.query(Category).filter(Category.user_id == user.id).all()
            cat_names = [c.name for c in categories]

            # 3. AI Extraction
            data = await extract_intent(text, cat_names)
            data['available_categories'] = cat_names

            # 4. Route Intent
            intent = data.get('intent')

            if intent == "log_transaction":
                await process_logging(message, data, user, cat_names, state)
            elif intent == "query":
                await process_query(message, data, user)
            elif intent == "delete_last":
                await process_deletion(message, user)
            elif intent == "correct_last":
                await start_correction(message, user, state)
            else:
                await message.answer(
                    f"💭 I understood: _\"{text}\"_\n\n"
                    f"But I'm not sure what to do with it. Try:\n"
                    f"• 'Received 500,000 from client'\n"
                    f"• 'Spent 50,000 on logistics'\n"
                    f"• 'How much did I spend this week?'\n"
                    f"• 'Delete last transaction'\n"
                    f"• 'Correct the last one'",
                    parse_mode="Markdown"
                )

    except Exception as e:
        logger.error(f"Voice processing error: {e}")
        await processing_msg.delete()
        await message.answer("❌ Sorry, I couldn't process that. Please try again or type it out.")


async def process_logging(message, data, user, cat_names, state):
    """Process transaction logging with intelligent handling"""

    confidence = data.get('confidence', 'medium')
    missing_fields = data.get('missing_fields', [])

    # ===== CRITICAL VALIDATION =====

    # 1. Must have amount
    if not data.get('amount'):
        await message.answer(
            "💭 I need to know the amount.\n"
            "Please tell me: _How much was it?_",
            parse_mode="Markdown"
        )
        await state.update_data(pending=data, waiting_for='amount')
        await state.set_state(TransactionFlow.waiting_for_missing_info)
        return

    # 2. Must have type (income or expense)
    if not data.get('type'):
        # Smart guess based on keywords
        note = data.get('note', '').lower()

        # Income indicators
        if any(word in note for word in ['received', 'earned', 'income', 'revenue', 'sale', 'payment from']):
            data['type'] = 'income'
            logger.info(f"Auto-detected type: income from note: {note}")

        # Expense indicators
        elif any(word in note for word in ['spent', 'paid', 'bought', 'purchase', 'cost', 'expense']):
            data['type'] = 'expense'
            logger.info(f"Auto-detected type: expense from note: {note}")

        # Still unclear - ask user
        else:
            kb = ReplyKeyboardMarkup(
                keyboard=[
                    [KeyboardButton(text="💰 Income"), KeyboardButton(text="💸 Expense")]
                ],
                resize_keyboard=True,
                one_time_keyboard=True
            )

            await message.answer(
                f"💭 I got **{data['amount']:,} som**\n\n"
                f"Is this money coming in (Income) or going out (Expense)?",
                reply_markup=kb,
                parse_mode="Markdown"
            )
            await state.update_data(pending=data, waiting_for='type')
            await state.set_state(TransactionFlow.waiting_for_missing_info)
            return

    # ===== CATEGORY HANDLING =====

    category_name = data.get('category', '').strip()

    # If no category provided
    if not category_name or category_name.lower() in ['none', 'no category', '']:
        # Check confidence level
        if confidence == 'high':
            # High confidence but no category - use "Others"
            await save_transaction_with_confirmation(message, data, user, use_others=True)
            return
        else:
            # Low/medium confidence - ask for category
            await ask_for_category_selection(message, data, user, state)
            return

    # Category provided - validate it exists
    with SessionLocal() as db:
        cat = db.query(Category).filter(
            Category.user_id == user.id,
            func.lower(Category.name) == func.lower(category_name)
        ).first()

        # Partial match if exact fails
        if not cat:
            cat = db.query(Category).filter(
                Category.user_id == user.id,
                Category.name.ilike(f"%{category_name}%")
            ).first()

        # Category not found - offer to create custom
        if not cat:
            await offer_create_custom_category(message, data, user, category_name, state)
            return

    # All good - save transaction
    await save_transaction_with_confirmation(message, data, user)


async def ask_for_category_selection(message, data, user, state):
    """Show category picker when category is unclear"""

    with SessionLocal() as db:
        tx_type = data.get('type', 'expense')
        categories = db.query(Category).filter(
            Category.user_id == user.id,
            Category.type == tx_type
        ).order_by(Category.name).all()

        if not categories:
            # No categories exist - use Others
            await save_transaction_with_confirmation(message, data, user, use_others=True)
            return

        # Build keyboard (2 columns)
        keyboard = []
        row = []
        for i, cat in enumerate(categories):
            row.append(KeyboardButton(text=cat.name))
            if len(row) == 2 or i == len(categories) - 1:
                keyboard.append(row)
                row = []

        keyboard.append([KeyboardButton(text="➕ Create New Category")])
        keyboard.append([KeyboardButton(text="⏩ Skip (use Others)")])

        kb = ReplyKeyboardMarkup(keyboard=keyboard, resize_keyboard=True, one_time_keyboard=True)

        await state.update_data(pending_transaction=data, waiting_for='category')
        await state.set_state(TransactionFlow.waiting_for_missing_info)

        await message.answer(
            f"💭 I got **{data['amount']:,} som** as {data['type']}\n\n"
            f"Which category should I use?",
            reply_markup=kb,
            parse_mode="Markdown"
        )


async def offer_create_custom_category(message, data, user, suggested_name, state):
    """Offer to create a custom category when not found"""

    kb = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text=f"✅ Create '{suggested_name.title()}'",
                                 callback_data=f"create_cat:{suggested_name}"),
            InlineKeyboardButton(text="❌ Choose Existing", callback_data="choose_existing")
        ],
        [
            InlineKeyboardButton(text="⏩ Skip (use Others)", callback_data="use_others")
        ]
    ])

    await state.update_data(pending_transaction=data, suggested_category=suggested_name)

    await message.answer(
        f"💭 Category **'{suggested_name}'** doesn't exist yet.\n\n"
        f"Would you like me to create it?",
        reply_markup=kb,
        parse_mode="Markdown"
    )


async def save_transaction_with_confirmation(message, data, user, use_others=False):
    """Save transaction and show confirmation with edit/delete options"""

    with SessionLocal() as db:
        # Find or create category
        category_name = data.get('category', '').strip()
        cat = None

        if not use_others and category_name:
            # Try to find existing category
            cat = db.query(Category).filter(
                Category.user_id == user.id,
                func.lower(Category.name) == func.lower(category_name)
            ).first()

            if not cat:
                cat = db.query(Category).filter(
                    Category.user_id == user.id,
                    Category.name.ilike(f"%{category_name}%")
                ).first()

        # Use or create "Others" if needed
        if not cat:
            tx_type = data['type']
            cat = db.query(Category).filter(
                Category.user_id == user.id,
                func.lower(Category.name) == 'others',
                Category.type == tx_type
            ).first()

            if not cat:
                cat = Category(
                    user_id=user.id,
                    name="Others",
                    type=tx_type,
                    is_custom=False
                )
                db.add(cat)
                db.commit()
                db.refresh(cat)

        # Create transaction
        new_tx = Transaction(
            user_id=user.id,
            amount=data['amount'],
            type=data['type'],
            category_id=cat.id,
            date=datetime.strptime(data['date'], '%Y-%m-%d').date(),
            note=data.get('note', ''),
            created_at=datetime.now(),
            updated_at=datetime.now()
        )
        db.add(new_tx)
        db.commit()
        db.refresh(new_tx)

        # Store in memory for corrections
        user_mem[user.id] = {
            'transaction_id': new_tx.id,
            'data': data
        }

        # Generate confirmation
        data['category'] = cat.name
        confirmation = await generate_confirmation_message(data)

        # Add inline buttons for edit/delete
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [
                InlineKeyboardButton(text="✏️ Edit", callback_data=f"edit_tx:{new_tx.id}"),
                InlineKeyboardButton(text="🗑️ Delete", callback_data=f"delete_tx:{new_tx.id}")
            ]
        ])

        from main import get_main_menu
        await message.answer(confirmation, reply_markup=kb, parse_mode="Markdown")
        # Send main menu in separate message
        await message.answer("What's next?", reply_markup=get_main_menu())


async def start_correction(message, user, state):
    """Start correction flow for last transaction"""

    mem_data = user_mem.get(user.id)
    if not mem_data:
        await message.answer("💭 I don't have any recent transaction to correct.")
        return

    tx_id = mem_data.get('transaction_id')

    with SessionLocal() as db:
        tx = db.query(Transaction).filter(Transaction.id == tx_id).first()
        if not tx:
            await message.answer("❌ Transaction not found")
            return

        # Show current transaction details with edit options
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [
                InlineKeyboardButton(text="💵 Change Amount", callback_data=f"edit_amount:{tx_id}"),
                InlineKeyboardButton(text="📂 Change Category", callback_data=f"edit_category:{tx_id}")
            ],
            [
                InlineKeyboardButton(text="📅 Change Date", callback_data=f"edit_date:{tx_id}"),
                InlineKeyboardButton(text="📝 Edit Note", callback_data=f"edit_note:{tx_id}")
            ],
            [
                InlineKeyboardButton(text="🔄 Change Type", callback_data=f"edit_type:{tx_id}"),
                InlineKeyboardButton(text="❌ Cancel", callback_data="cancel_edit")
            ]
        ])

        cat_name = tx.category.name if tx.category else "Uncategorized"

        await message.answer(
            f"✏️ **Edit Transaction**\n\n"
            f"💵 Amount: {tx.amount:,.0f} som\n"
            f"📂 Category: {cat_name}\n"
            f"📅 Date: {tx.date.strftime('%B %d, %Y')}\n"
            f"📝 Note: {tx.note or '_No note_'}\n"
            f"🔄 Type: {tx.type}\n\n"
            f"What would you like to change?",
            reply_markup=kb,
            parse_mode="Markdown"
        )


async def process_deletion(message, user):
    """Delete last transaction with confirmation"""

    mem_data = user_mem.get(user.id)
    if not mem_data:
        await message.answer("💭 I don't have any recent transaction to delete.")
        return

    tx_id = mem_data.get('transaction_id')

    with SessionLocal() as db:
        tx = db.query(Transaction).filter(Transaction.id == tx_id).first()
        if tx:
            amount = tx.amount
            tx_type = tx.type
            cat_name = tx.category.name if tx.category else "Uncategorized"

            db.delete(tx)
            db.commit()
            user_mem[user.id] = None

            await message.answer(
                f"🗑️ **Transaction Deleted**\n\n"
                f"Removed: {amount:,.0f} som {tx_type}\n"
                f"Category: {cat_name}",
                parse_mode="Markdown"
            )
        else:
            await message.answer("❌ Transaction not found")


async def process_query(message, data, user):
    """Handle financial queries with intelligent data analysis"""

    query_target = data.get('query_target', '').lower()
    original_text = message.text.lower() if message.text else query_target

    with SessionLocal() as db:
        # ===== 1. DETERMINE TIME PERIOD =====
        now = datetime.now()
        start_date = None
        end_date = None
        period_name = "all time"

        # Today
        if any(word in original_text for word in ["today", "сегодня"]):
            start_date = now.date()
            end_date = now.date()
            period_name = "today"

        # Yesterday
        elif any(word in original_text for word in ["yesterday", "вчера"]):
            start_date = (now - timedelta(days=1)).date()
            end_date = (now - timedelta(days=1)).date()
            period_name = "yesterday"

        # This week
        elif any(word in original_text for word in ["this week", "эта неделя", "week"]):
            start_date = (now - timedelta(days=now.weekday())).date()
            end_date = now.date()
            period_name = "this week"

        # Last week
        elif any(word in original_text for word in ["last week", "прошлая неделя"]):
            start_of_last_week = (now - timedelta(days=now.weekday() + 7)).date()
            end_of_last_week = (now - timedelta(days=now.weekday() + 1)).date()
            start_date = start_of_last_week
            end_date = end_of_last_week
            period_name = "last week"

        # This month
        elif any(word in original_text for word in ["this month", "этот месяц", "month"]):
            start_date = now.replace(day=1).date()
            end_date = now.date()
            period_name = "this month"

        # Last month
        elif any(word in original_text for word in ["last month", "прошлый месяц"]):
            first_of_this_month = now.replace(day=1).date()
            last_month = first_of_this_month - timedelta(days=1)
            start_date = last_month.replace(day=1)
            end_date = last_month
            period_name = "last month"

        # This year
        elif any(word in original_text for word in ["this year", "этот год", "year"]):
            start_date = now.replace(month=1, day=1).date()
            end_date = now.date()
            period_name = "this year"

        # Last N days
        days_pattern = re.search(r'last (\d+) days?', original_text)
        if days_pattern:
            days_count = int(days_pattern.group(1))
            start_date = (now - timedelta(days=days_count)).date()
            end_date = now.date()
            period_name = f"last {days_count} days"

        # ===== 2. BUILD BASE QUERY =====
        query = db.query(Transaction).filter(Transaction.user_id == user.id)

        if start_date:
            query = query.filter(Transaction.date >= start_date)
        if end_date:
            query = query.filter(Transaction.date <= end_date)

        # ===== 3. DETERMINE TRANSACTION TYPE =====
        tx_type = None
        metric = "transactions"

        # Income keywords
        if any(word in original_text for word in [
            "earn", "earned", "income", "revenue", "received", "made",
            "заработали", "доход", "получили"
        ]):
            query = query.filter(Transaction.type == 'income')
            tx_type = 'income'
            metric = "earned"

        # Expense keywords
        elif any(word in original_text for word in [
            "spend", "spent", "expense", "paid", "cost",
            "потратили", "расход", "затраты"
        ]):
            query = query.filter(Transaction.type == 'expense')
            tx_type = 'expense'
            metric = "spent"

        # ===== 4. CHECK FOR CATEGORY FILTER =====
        categories = db.query(Category).filter(Category.user_id == user.id).all()
        matched_category = None

        for cat in categories:
            if cat.name.lower() in original_text:
                query = query.filter(Transaction.category_id == cat.id)
                matched_category = cat.name
                break

        # ===== 5. EXECUTE QUERY & CALCULATE =====
        transactions = query.order_by(Transaction.date.desc()).all()

        if not transactions:
            filters = []
            if matched_category:
                filters.append(f"category '{matched_category}'")
            if tx_type:
                filters.append(tx_type)
            if period_name != "all time":
                filters.append(period_name)

            filter_text = " for " + ", ".join(filters) if filters else ""

            await message.answer(
                f"📊 **No Data Found**\n\n"
                f"I couldn't find any transactions{filter_text}.",
                parse_mode="Markdown"
            )
            return

        # Calculate totals
        total_amount = sum(t.amount for t in transactions)
        count = len(transactions)

        # Get category breakdown if no specific category was requested
        category_breakdown = {}
        if not matched_category:
            for tx in transactions:
                cat_name = tx.category.name if tx.category else "Uncategorized"
                category_breakdown[cat_name] = category_breakdown.get(cat_name, 0) + tx.amount

        # ===== 6. FORMAT RESPONSE =====

        # Choose emoji
        if tx_type == 'income':
            emoji = "💰"
            action = "earned"
        elif tx_type == 'expense':
            emoji = "💸"
            action = "spent"
        else:
            emoji = "📊"
            action = "total"

        # Build response
        response = f"{emoji} **Financial Report**\n\n"

        # Main stat
        if matched_category:
            response += f"**{matched_category}:** {total_amount:,.0f} som\n"
        else:
            response += f"**Total {action}:** {total_amount:,.0f} som\n"

        response += f"📅 Period: {period_name.title()}\n"
        response += f"🧾 Transactions: {count}\n"

        # Category breakdown (top 5)
        if category_breakdown and len(category_breakdown) > 1:
            response += f"\n**Breakdown by Category:**\n"
            sorted_cats = sorted(category_breakdown.items(), key=lambda x: x[1], reverse=True)
            for i, (cat_name, amount) in enumerate(sorted_cats[:5], 1):
                percentage = (amount / total_amount * 100) if total_amount > 0 else 0
                response += f"{i}. {cat_name}: {amount:,.0f} som ({percentage:.1f}%)\n"

            if len(sorted_cats) > 5:
                response += f"_...and {len(sorted_cats) - 5} more categories_\n"

        # Average per transaction
        avg = total_amount / count if count > 0 else 0
        response += f"\n📈 Average: {avg:,.0f} som per transaction"

        # Comparison with previous period
        if period_name in ["this week", "this month", "this year"]:
            prev_total = await get_previous_period_total(db, user.id, period_name, tx_type, matched_category)
            if prev_total is not None and prev_total > 0:
                change = total_amount - prev_total
                change_pct = (change / prev_total * 100) if prev_total > 0 else 0

                if change > 0:
                    trend = "📈" if tx_type == 'income' else "⚠️"
                    change_text = f"+{change:,.0f}"
                elif change < 0:
                    trend = "⚠️" if tx_type == 'income' else "📉"
                    change_text = f"{change:,.0f}"
                else:
                    trend = "➡️"
                    change_text = "No change"

                response += f"\n\n{trend} vs previous period: {change_text} som ({change_pct:+.1f}%)"

        await message.answer(response, parse_mode="Markdown")


async def get_previous_period_total(db, user_id, period_name, tx_type, category_name):
    """Calculate total for previous period for comparison"""

    now = datetime.now()
    start_date = None
    end_date = None

    if period_name == "this week":
        start_date = (now - timedelta(days=now.weekday() + 7)).date()
        end_date = (now - timedelta(days=now.weekday() + 1)).date()

    elif period_name == "this month":
        first_of_this_month = now.replace(day=1).date()
        last_month = first_of_this_month - timedelta(days=1)
        start_date = last_month.replace(day=1)
        end_date = last_month

    elif period_name == "this year":
        start_date = now.replace(year=now.year - 1, month=1, day=1).date()
        end_date = now.replace(year=now.year - 1, month=12, day=31).date()

    if not start_date:
        return None

    query = db.query(func.sum(Transaction.amount)).filter(
        Transaction.user_id == user_id,
        Transaction.date >= start_date,
        Transaction.date <= end_date
    )

    if tx_type:
        query = query.filter(Transaction.type == tx_type)

    if category_name:
        cat = db.query(Category).filter(
            Category.user_id == user_id,
            func.lower(Category.name) == func.lower(category_name)
        ).first()
        if cat:
            query = query.filter(Transaction.category_id == cat.id)

    result = query.scalar()
    return result or 0


async def handle_missing_info_logic(message: types.Message, state: FSMContext):
    """Handle follow-up responses when info was missing"""

    data = await state.get_data()
    pending = data.get('pending_transaction') or data.get('pending')
    waiting_for = data.get('waiting_for')

    if not pending:
        await message.answer("❌ Sorry, I lost track. Please start over.")
        await state.clear()
        return

    user_input = message.text.strip()

    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()

        # Handle type selection
        if waiting_for == 'type':
            if "income" in user_input.lower():
                pending['type'] = 'income'
            elif "expense" in user_input.lower():
                pending['type'] = 'expense'
            else:
                await message.answer("💭 Please choose 'Income' or 'Expense'")
                return

            await ask_for_category_selection(message, pending, user, state)
            return

        # Handle category selection
        if waiting_for == 'category':
            if user_input == "⏩ Skip (use Others)":
                await save_transaction_with_confirmation(message, pending, user, use_others=True)
                await state.clear()
                return

            if user_input == "➕ Create New Category":
                await message.answer(
                    "📝 What should I name this new category?\n"
                    "_Example: Equipment, Travel, Consulting_",
                    parse_mode="Markdown"
                )
                await state.update_data(creating_custom_category=True)
                await state.set_state(TransactionFlow.waiting_for_custom_category_name)
                return

            cat = db.query(Category).filter(
                Category.user_id == user.id,
                func.lower(Category.name) == func.lower(user_input)
            ).first()

            if cat:
                pending['category'] = cat.name
                await save_transaction_with_confirmation(message, pending, user)
                await state.clear()
                return
            else:
                await message.answer("💭 Category not found. Please select from the buttons above.")
                return

        # Handle amount input
        if waiting_for == 'amount':
            amount_match = re.search(r'(\d+[\d,]*)', user_input)
            if amount_match:
                pending['amount'] = int(amount_match.group(1).replace(',', ''))

                # Check if we now have all needed info
                if pending.get('type'):
                    await save_transaction_with_confirmation(message, pending, user)
                    await state.clear()
                else:
                    # Still need type
                    kb = ReplyKeyboardMarkup(
                        keyboard=[
                            [KeyboardButton(text="💰 Income"), KeyboardButton(text="💸 Expense")]
                        ],
                        resize_keyboard=True,
                        one_time_keyboard=True
                    )
                    await message.answer(
                        f"💭 Got it: **{pending['amount']:,} som**\n\n"
                        f"Is this Income or Expense?",
                        reply_markup=kb,
                        parse_mode="Markdown"
                    )
                    await state.update_data(pending=pending, waiting_for='type')
            else:
                await message.answer("💭 Please send me just the number.")
            return

    await message.answer("💭 I'm still missing some information. Please tell me the full transaction again.")
    await state.clear()


async def handle_custom_category_name(message: types.Message, state: FSMContext):
    """Receive custom category name"""

    category_name = message.text.strip().title()

    data = await state.get_data()
    pending = data.get('pending_transaction')

    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()

        existing = db.query(Category).filter(
            Category.user_id == user.id,
            func.lower(Category.name) == func.lower(category_name)
        ).first()

        if existing:
            await message.answer(f"❌ Category '{category_name}' already exists! Using it.")
            pending['category'] = existing.name
            await save_transaction_with_confirmation(message, pending, user)
            await state.clear()
            return

        kb = ReplyKeyboardMarkup(
            keyboard=[
                [KeyboardButton(text="💰 Income Category"), KeyboardButton(text="💸 Expense Category")]
            ],
            resize_keyboard=True,
            one_time_keyboard=True
        )

        await state.update_data(custom_category_name=category_name)
        await state.set_state(TransactionFlow.waiting_for_custom_category_type)

        await message.answer(
            f"📂 Category name: **{category_name}**\n\n"
            f"Is this an Income or Expense category?",
            reply_markup=kb,
            parse_mode="Markdown"
        )


async def handle_custom_category_type(message: types.Message, state: FSMContext):
    """Create custom category and save transaction"""

    data = await state.get_data()
    category_name = data.get('custom_category_name')
    pending = data.get('pending_transaction')

    if "income" in message.text.lower():
        cat_type = "income"
    elif "expense" in message.text.lower():
        cat_type = "expense"
    else:
        await message.answer("💭 Please choose 'Income Category' or 'Expense Category'")
        return

    with SessionLocal() as db:
        user = db.query(User).filter(User.telegram_id == str(message.from_user.id)).first()

        new_cat = Category(
            user_id=user.id,
            name=category_name,
            type=cat_type,
            is_custom=True
        )
        db.add(new_cat)
        db.commit()
        db.refresh(new_cat)

        await message.answer(f"✅ Created new category: **{category_name}** ({cat_type})", parse_mode="Markdown")

        pending['category'] = category_name
        await save_transaction_with_confirmation(message, pending, user)
        await state.clear()