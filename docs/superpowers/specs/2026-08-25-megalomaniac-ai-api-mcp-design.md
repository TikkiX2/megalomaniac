# Megalomaniac Pro — AI + API + MCP Design Spec

## Overview

Major upgrade to Megalomaniac Pro adding AI intelligence, a REST API, and an MCP server. User provides their own OpenAI-compatible API key + URL. All modules exposed via API and MCP.

## Goals

1. Update all dependencies to latest stable
2. REST API for all 7 modules (internal + external consumers)
3. AI features: auto-categorization, smart suggestions, NL search
4. In-app AI assistant: conversational chatbot with streaming
5. Proactive AI agents: background suggestions and notifications
6. MCP server: read + write access for external AI tools (Claude, Cursor, etc.)

## Architecture

### Dependency Updates
- PHP 8.2 → 8.4
- Laravel 12 → latest 12.x
- All composer/npm packages to latest stable
- New packages: `laravel/ai`, `laravel/mcp`, `laravel/sanctum`

### REST API
- Versioned: `/api/v1/`
- Auth: Sanctum personal access tokens (same token for MCP)
- Controllers: `app/Http/Controllers/Api/V1/`
- Resources: `app/Http/Resources/`
- Requests: `app/Http/Requests/Api/`
- Services: `app/Services/` (shared between web + API)
- Rate limiting via Laravel throttle middleware

### AI System (using `laravel/ai`)
- Provider: `openai-compatible` driver with user-configured URL + key
- Config: `config/ai.php` + user settings in DB
- Agents: `app/Ai/Agents/` — MegalomaniacAgent (main), specialized sub-agents
- Tools: `app/Ai/Tools/` — WorkoutQuery, FinanceQuery, NutritionQuery, Action tools
- Conversations: `RemembersConversations` trait → `agent_conversations` + `agent_conversation_messages` tables
- Streaming: SSE via `->stream()` method
- Context: user's recent data injected into system prompt

### MCP Server (using `laravel/mcp`)
- Endpoint: `Mcp::web('/mcp/megalomaniac', MegalomaniacServer::class)`
- Auth: Sanctum (same tokens as REST API)
- Server: `app/Mcp/Servers/MegalomaniacServer.php`
- Tools: `app/Mcp/Tools/` — per-module read/write tools
- Resources: `app/Mcp/Resources/` — read-only data access
- Transport: HTTP (web server)

## Data Model Additions

### User Settings (AI config)
- `ai_provider_url` — OpenAI-compatible API URL
- `ai_provider_key` — API key (encrypted)
- `ai_model` — model name to use
- `ai_enabled` — boolean toggle

### Conversations (from laravel/ai migrations)
- `agent_conversations` — id, user_id, participant_type, participant_id, metadata, timestamps
- `agent_conversation_messages` — id, conversation_id, role, content, metadata, timestamps

### Agent Suggestions (proactive agents)
- `agent_suggestions` — id, user_id, type, title, content, data, dismissed_at, timestamps

## Module Coverage

### API Endpoints (per module)
- Fitness: workouts, exercises, routines, sets
- Nutrition: foods, meal-logs, meal-items
- Supplements: items, logs
- Grocery: items, history, consume, bulk-restock
- Finance: purchases, incomes, debts, credit-cards, currencies, exchange-rates, income-sources, categories, withdrawals, savings-reserves, currency-exchanges, statistics
- Freelance: clients, projects, tasks, comments, quotes, payments
- Personal: projects, tasks, milestones, properties, saved-views

### MCP Tools (per module)
- Read tools: query/list/get for each resource type
- Write tools: create/update/delete for each resource type
- Action tools: consume grocery, log supplement, add workout set, etc.

### AI Tools (agent abilities)
- WorkoutQueryTool — search user's workout history
- FinanceQueryTool — search user's financial data
- NutritionQueryTool — search user's meal logs
- GroceryQueryTool — search user's grocery items
- ActionTool — create workouts, log meals, add purchases, etc.
- InsightTool — generate AI insights from user data

## Testing Strategy

- Phase 1: `php artisan test --compact` after each dependency update
- Phase 2: Feature tests for every API endpoint (Pest)
- Phase 3: Unit tests for AI services (mock external API)
- Phase 4: Feature tests for conversation CRUD + mock AI responses
- Phase 5: Unit tests for each agent
- Phase 6: Integration tests for MCP tools

## Security

- API keys encrypted in DB (encrypted cast)
- Sanctum tokens scoped per module
- Rate limiting on all API endpoints
- MCP server requires authenticated requests
- AI conversations scoped to authenticated user
- No secrets in logs or responses
