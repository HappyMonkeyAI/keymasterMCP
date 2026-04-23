from typing import Optional, Any
from pydantic import BaseModel, Field


class ChatMessage(BaseModel):
    role: str
    content: str


class ChatCompletionRequest(BaseModel):
    model: str
    messages: list[ChatMessage]
    temperature: Optional[float] = 1.0
    top_p: Optional[float] = 1.0
    n: Optional[int] = 1
    stream: Optional[bool] = False
    stop: Optional[list[str]] = None
    max_tokens: Optional[int] = None
    presence_penalty: Optional[float] = None
    frequency_penalty: Optional[float] = None
    user: Optional[str] = None


class ChatCompletionResponse(BaseModel):
    id: str
    object: str = "chat.completion"
    created: int
    model: str
    choices: list[dict[str, Any]]
    usage: Optional[dict[str, int]] = None


class CompletionRequest(BaseModel):
    model: str
    prompt: str | list[str]
    temperature: Optional[float] = 1.0
    max_tokens: Optional[int] = 2048
    n: Optional[int] = 1
    stream: Optional[bool] = False


class ServiceInfo(BaseModel):
    name: str
    display_name: Optional[str] = None
    group_name: Optional[str] = None
    description: Optional[str] = None
    configured: bool


class ClientInfo(BaseModel):
    client_id: str
    name: Optional[str]
    email: Optional[str] = None
    role: str = "developer"
    created_at: str
    last_used_at: Optional[str]


class CreateClientRequest(BaseModel):
    client_id: str
    name: Optional[str] = None
    email: Optional[str] = None
    role: str = "developer"


class CreateClientResponse(BaseModel):
    client_id: str
    client_secret: str


class AddKeyRequest(BaseModel):
    service: str
    api_key: Optional[str] = None


class RotateKeyRequest(BaseModel):
    service: str
    new_api_key: str


class ProjectRequest(BaseModel):
    name: str
    description: Optional[str] = None
    type: str = "secrets"


class ProjectResponse(BaseModel):
    id: int
    name: str
    slug: Optional[str] = None
    description: Optional[str]
    type: str = "secrets"
    created_at: str
    updated_at: str


class ProjectDetailResponse(BaseModel):
    id: int
    name: str
    slug: Optional[str] = None
    description: Optional[str]
    type: str = "secrets"
    created_at: str
    updated_at: str
    credentials: list[str]
    ips: list[str]
    api_key: Optional[str] = None


class OrganizationInfo(BaseModel):
    name: str
    slug: str
    created_at: str


class UpdateOrganizationRequest(BaseModel):
    name: str
    slug: str


class AddCredentialRequest(BaseModel):
    service: str


class AddIPRequest(BaseModel):
    ip_address: str


class CredentialGroupRequest(BaseModel):
    name: str
    description: Optional[str] = None


class RegisterCredentialRequest(BaseModel):
    service: str
    display_name: Optional[str] = None
    group_id: Optional[int] = None
    description: Optional[str] = None
