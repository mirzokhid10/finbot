from sqlalchemy.exc import IntegrityError
from db.database import SessionLocal
from db.models import User
import logging
from aiogram import Bot
import os
from datetime import datetime
import secrets

BOT_TOKEN = os.getenv('BOT_TOKEN')
bot = Bot(token=BOT_TOKEN)
logger = logging.getLogger(__name__)


async def register_user_if_not_exists(telegram_payload):
    """
    Registers a user from Telegram if they don't exist.
    Returns: (message: str, is_new_user: bool)
    """
    db = SessionLocal()
    try:
        telegram_id = str(telegram_payload.get('id'))

        # 1. Check if user exists
        user = db.query(User).filter(User.telegram_id == telegram_id).first()

        if user:
            logger.info(f"Existing user logged in: {telegram_id}")
            # Return: (message, is_new=False)
            return f"Welcome back, {user.name}! 👋", False

        # 2. Create new user
        first_name = telegram_payload.get('first_name', '')
        last_name = telegram_payload.get('last_name') or ""  # Fix for "None" string
        full_name = f"{first_name} {last_name}".strip() or "Telegram User"

        # 3. Get profile image
        image_url = None
        try:
            photos = await bot.get_user_profile_photos(user_id=telegram_payload.get('id'), limit=1)
            if photos.total_count > 0:
                file_id = photos.photos[0][-1].file_id
                file = await bot.get_file(file_id)
                image_url = f"https://api.telegram.org/file/bot{BOT_TOKEN}/{file.file_path}"
        except Exception as e:
            logger.error(f"Image fetch failed: {e}")

        # 4. Create user record
        new_user = User(
            name=full_name,
            telegram_id=telegram_id,
            username=telegram_payload.get('username'),
            image=image_url,
            remember_token=secrets.token_urlsafe(40),
            created_at=datetime.now(),
            updated_at=datetime.now()
        )

        db.add(new_user)
        db.commit()
        db.refresh(new_user)

        logger.info(f"New user registered: {telegram_id} ({full_name})")
        # Return: (message, is_new=True) ← FIX: Must return 2 values!
        return f"✅ Registration successful! Nice to meet you, {full_name}.", True

    except IntegrityError as e:
        db.rollback()
        logger.error(f"Integrity error during registration for {telegram_id}: {e}")

        # Try to fetch the user again (race condition)
        user = db.query(User).filter(User.telegram_id == telegram_id).first()
        if user:
            return f"Welcome back, {user.name}! 👋", False  # ← FIX: Return 2 values

        return "⚠️ Registration failed. Please try again.", False  # ← FIX: Return 2 values

    except Exception as e:
        db.rollback()
        logger.error(f"Unexpected error during registration: {e}", exc_info=True)
        return "❌ An error occurred during registration. Please contact support.", False  # ← FIX: Return 2 values

    finally:
        db.close()