# services/ai_service.py

import os
import json
from datetime import datetime
from groq import AsyncGroq

client = AsyncGroq(api_key=os.getenv("GROQ_API_KEY"))


async def transcribe_voice(file_path):
    """Use Groq's FREE Whisper API"""
    with open(file_path, "rb") as audio_file:
        transcript = await client.audio.transcriptions.create(
            model="whisper-large-v3",
            file=audio_file,
            response_format="text"
        )
    return transcript


async def extract_intent(text, categories_list):
    """Enhanced extraction with correction intent"""
    prompt = f"""
    Analyze this financial request: "{text}"
    Current Date: {datetime.now().strftime('%Y-%m-%d')}
    Available Categories: {', '.join(categories_list)}

    Return ONLY a JSON object:
    {{
        "intent": "log_transaction" | "query" | "delete_last" | "correct_last",
        "type": "income" | "expense" | null,
        "amount": number | null,
        "category": best match from available list | null,
        "date": "YYYY-MM-DD",
        "note": brief description,
        "query_target": exact copy of the question if intent is query,
        "confidence": "high" | "medium" | "low",
        "missing_fields": ["field1", "field2"]
    }}

    Intent Detection:
    - "query" if asking "how much", "what did", "show me", "total"
    - "log_transaction" if stating amount or transaction happened
    - "delete_last" if saying "delete", "remove last", "undo"
    - "correct_last" if saying "edit", "correct", "change", "fix last transaction"

    Confidence Rules:
    - "high" if amount, type, and category are all clear
    - "medium" if missing category but amount and type are clear
    - "low" if multiple fields unclear or ambiguous

    Examples:
    "50,000 received today" -> {{"intent": "log_transaction", "type": "income", "amount": 50000, "category": null, "confidence": "medium", "missing_fields": ["category"]}}
    "Spent something on logistics" -> {{"intent": "log_transaction", "type": "expense", "amount": null, "category": "Logistics", "confidence": "low", "missing_fields": ["amount"]}}
    "Edit the last transaction" -> {{"intent": "correct_last"}}
    """

    response = await client.chat.completions.create(
        model="llama-3.3-70b-versatile",
        messages=[
            {
                "role": "system",
                "content": "You are a financial assistant. Return ONLY valid JSON, no markdown."
            },
            {
                "role": "user",
                "content": prompt
            }
        ],
        temperature=0.1,
        max_tokens=600
    )

    content = response.choices[0].message.content.strip()

    if content.startswith("```json"):
        content = content.replace("```json", "").replace("```", "").strip()

    return json.loads(content)


async def generate_confirmation_message(transaction_data):
    """Generate natural confirmation message"""
    amount = f"{transaction_data['amount']:,}" if transaction_data.get('amount') else "?"
    tx_type = transaction_data.get('type', 'transaction')
    category = transaction_data.get('category', 'no category')
    note = transaction_data.get('note', '')
    date = transaction_data.get('date', datetime.now().strftime('%Y-%m-%d'))

    # Parse date for natural display
    try:
        date_obj = datetime.strptime(date, '%Y-%m-%d')
        if date_obj.date() == datetime.now().date():
            date_display = "today"
        elif date_obj.date() == (datetime.now().date() - datetime.timedelta(days=1)):
            date_display = "yesterday"
        else:
            date_display = f"on {date_obj.strftime('%B %d')}"
    except:
        date_display = date

    # Build natural message
    emoji = "💰" if tx_type == "income" else "💸"

    msg = f"{emoji} Got it!\n\n"
    msg += f"**{amount} som** {tx_type} {date_display}\n"
    msg += f"📂 Category: {category}\n"

    if note and note.lower() != category.lower():
        msg += f"📝 Note: _{note}_\n"

    return msg


async def generate_clarification_question(data, missing_fields):
    """Generate smart follow-up questions"""
    if not missing_fields:
        return None

    # Prioritize what to ask first
    if "amount" in missing_fields:
        return "💭 I heard you, but how much was it? Please tell me the amount."

    if "type" in missing_fields:
        return "💭 Was this money coming in (income) or going out (expense)?"

    if "category" in missing_fields:
        categories_list = data.get('available_categories', [])
        if categories_list:
            income_cats = [c for c in categories_list if
                           'income' in c.lower() or c in ['Sales', 'Salary', 'Investment']]
            expense_cats = [c for c in categories_list if
                            'expense' in c.lower() or c in ['Logistics', 'Rent', 'Utilities', 'Marketing', 'Salaries']]

            relevant_cats = income_cats if data.get('type') == 'income' else expense_cats

            if relevant_cats:
                cat_list = ", ".join(relevant_cats[:5])
                return f"💭 Which category does this belong to? For example: {cat_list}"

        return "💭 What category should I use for this transaction?"

    return None