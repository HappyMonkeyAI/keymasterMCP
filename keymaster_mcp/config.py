import os
from functools import lru_cache
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    keymaster_vault_path: str = "./vault"
    database_path: str = "./keymaster.db"
    hmac_secret: str = "change-me-in-production"
    log_level: str = "INFO"
    timestamp_tolerance_seconds: int = 30

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"


@lru_cache
def get_settings() -> Settings:
    return Settings()
