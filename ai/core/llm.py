"""
LLM client for the digiMind chatbot.
Uses Groq's free-tier, OpenAI-compatible chat completions API.
"""
import json
import logging
import os
import httpx

logger = logging.getLogger(__name__)

GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_MODEL   = os.getenv("GROQ_MODEL", "llama-3.3-70b-versatile")
GROQ_URL     = "https://api.groq.com/openai/v1/chat/completions"


def ask_llm_with_tools(
    system_prompt: str,
    question: str,
    history: list[dict] | None,
    tools_schema: list[dict],
    tool_executor,
    max_rounds: int = 4,
) -> str:
    """
    Runs the chat completion with function calling: the model can call tools
    to fetch real data before answering. tool_executor(name, args) -> dict.
    """
    if not GROQ_API_KEY:
        raise RuntimeError("GROQ_API_KEY manquant dans l'environnement")

    messages = [{"role": "system", "content": system_prompt}]
    for turn in (history or [])[-6:]:
        role = turn.get("role")
        content = turn.get("content")
        if role in ("user", "assistant") and content:
            messages.append({"role": role, "content": str(content)[:2000]})
    messages.append({"role": "user", "content": question[:2000]})

    for _ in range(max_rounds):
        resp = httpx.post(
            GROQ_URL,
            headers={
                "Authorization": f"Bearer {GROQ_API_KEY}",
                "Content-Type": "application/json",
            },
            json={
                "model": GROQ_MODEL,
                "messages": messages,
                "tools": tools_schema,
                "tool_choice": "auto",
                "temperature": 0.2,
                "max_tokens": 800,
            },
            timeout=30,
        )
        resp.raise_for_status()
        msg = resp.json()["choices"][0]["message"]

        tool_calls = msg.get("tool_calls")
        if not tool_calls:
            return (msg.get("content") or "").strip()

        messages.append(msg)
        for tc in tool_calls:
            fn_name = tc["function"]["name"]
            try:
                fn_args = json.loads(tc["function"].get("arguments") or "{}")
            except json.JSONDecodeError:
                fn_args = {}
            logger.info(f"[digiMind chat] tool call: {fn_name}({fn_args})")
            result = tool_executor(fn_name, fn_args)
            messages.append({
                "role": "tool",
                "tool_call_id": tc["id"],
                "content": json.dumps(result, default=str, ensure_ascii=False)[:4000],
            })

    return "Je n'ai pas pu terminer cette analyse — pouvez-vous reformuler votre question plus simplement ?"
