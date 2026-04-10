from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    # DB Connections - Vienen de .env
    ECOMMERCE_DB_URL: str
    BOT_RAG_DB_URL: str
    
    # Redis
    REDIS_URL: str
    
    # ML Model
    LOCAL_EMBEDDING_MODEL: str = "all-MiniLM-L6-v2"
    
    # APIs
    WHATSAPP_VERIFY_TOKEN: str
    WHATSAPP_TOKEN: str
    WHATSAPP_PHONE_ID: str
    DEEPSEEK_API_KEY: str
    APP_URL: str = "http://localhost:8000"

    class Config:
        env_file = ".env"
        extra = "ignore"

settings = Settings()
