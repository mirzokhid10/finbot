from sqlalchemy.orm import DeclarativeBase
from sqlalchemy import Column, String, Boolean, BigInteger, Numeric, Enum, Date, Text, DateTime, ForeignKey, func, Index, Integer
from sqlalchemy.orm import relationship

class Base(DeclarativeBase):
    pass


class User(Base):
    __tablename__ = "users"

    id = Column(BigInteger, primary_key=True, autoincrement=True, nullable=False)
    name = Column(String(255), nullable=False)
    telegram_id = Column(String(255), unique=True, nullable=False, index=True)
    telegram_sub = Column(String(255), unique=True, nullable=True)
    username = Column(String(255), nullable=True)
    email = Column(String(255), nullable=True)
    phone = Column(String(255), nullable=True)
    image = Column(String(255), nullable=True)
    password = Column(String(255), nullable=True)
    remember_token = Column(String(100), nullable=True)
    created_at = Column(DateTime, nullable=False, server_default=func.now())
    updated_at = Column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())

    transactions = relationship("Transaction", back_populates="user", cascade="all, delete-orphan")
    categories = relationship("Category", back_populates="user", cascade="all, delete-orphan")
    def __repr__(self):
        return f"<User(id={self.id}, telegram_id={self.telegram_id}, name={self.name})>"


class PasswordResetToken(Base):
    __tablename__ = 'password_reset_tokens'

    email = Column(String(255), primary_key=True)
    token = Column(String(255), nullable=False)
    created_at = Column(DateTime, nullable=True)


class Session(Base):
    __tablename__ = 'sessions'

    id = Column(String(255), primary_key=True)
    user_id = Column(BigInteger, ForeignKey('users.id'), nullable=True)
    ip_address = Column(String(45), nullable=True)
    user_agent = Column(Text, nullable=True)
    payload = Column(Text, nullable=False)
    last_activity = Column(Integer, nullable=False)

    __table_args__ = (
        Index('sessions_user_id_index', 'user_id'),
        Index('sessions_last_activity_index', 'last_activity'),
    )


class Category(Base):
    __tablename__ = 'categories'
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    type = Column(Enum('income', 'expense', name='category_types'), nullable=False)
    # Foreign Key to users table with Cascade on Delete
    user_id = Column(
        BigInteger,
        ForeignKey('users.id', ondelete='CASCADE'),
        nullable=False
    )

    is_custom = Column(Boolean, default=False, nullable=False)

    # Laravel Timestamps
    created_at = Column(DateTime, nullable=False, server_default=func.now())
    updated_at = Column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())

    # Optional: Relationship to User model
    user = relationship("User", back_populates="categories")
    transactions = relationship("Transaction", back_populates="category")


class Transaction(Base):
    __tablename__ = 'transactions'

    id = Column(BigInteger, primary_key=True, autoincrement=True)

    # User Foreign Key (onDelete='CASCADE')
    user_id = Column(
        BigInteger,
        ForeignKey('users.id', ondelete='CASCADE'),
        nullable=False
    )

    # Amount: Decimal (15, 2)
    amount = Column(Numeric(precision=15, scale=2), nullable=False)

    # Type: Enum ('income', 'expense')
    type = Column(Enum('income', 'expense', name='transaction_types'), nullable=False)

    # Category Foreign Key (nullable, onDelete='SET NULL')
    category_id = Column(
        BigInteger,
        ForeignKey('categories.id', ondelete='SET NULL'),
        nullable=True
    )

    # The actual date of the transaction
    date = Column(Date, nullable=False)

    # Note
    note = Column(Text, nullable=True)

    # Laravel Timestamps
    created_at = Column(DateTime, nullable=False, server_default=func.now())
    updated_at = Column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())
    # Relationships (Optional, but very helpful for queries)
    user = relationship("User", back_populates="transactions")
    category = relationship("Category", back_populates="transactions")