# Intent Routing Example

This example demonstrates the **intent + skill** system in a disaster-response context, using a simple keyword-based analyzer.

## What it demonstrates

- Defining `Intent` objects with keyword criteria.
- Registering `ISkill` implementations and associating them with intents.
- Using `Matcher` to:
  - Score intents against free-text input.
  - Select an intent.
  - Execute the corresponding skill and return a response.

The analyzer used here is intentionally simple and does not rely on external services.

## How to run

From the project root:

```bash
php examples/disaster_response/intent_routing/run.php "Requesting helicopter airlift for flooded hospital"
```

If no input is provided, a default airlift request sentence is used.

## Outputs

JSON to stdout with:

- `input`: the text that was analyzed.
- `scores`: map of intent label → score.
- `selected`: the label of the highest-scoring intent.
- `response`: the response produced by the selected skill (here, just echoes the message stored in context).

The library remains generic; this example is domain-specific and can be replaced or extended with more sophisticated analyzers (e.g., Naive Bayes, LLM-backed analyzers) without changing the core `Intent` / `Matcher` / `Skill` types.

