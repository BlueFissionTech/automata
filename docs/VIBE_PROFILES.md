# Vibe Profile Generation

Automata now supports a declarative `profile` attribute on generation-style eval tags.

Example:

```txt
{=content profile="profiles/editorial-book.vibe"}
```

Host bootstrap flow:

1. Construct `FillIn` with the default generation client.
2. Call `setProfilePaths([...])` when profile files should resolve from local directories.
3. Optionally call `registerProfileOverride($name, [...])` when a host wants a named profile alias to swap the generation driver and/or inject an inline prompt.
4. Run the template normally.

The runtime contract is:

- When `profile` is absent, Automata uses the default generation client.
- When `profile` points to a resolvable `.vibe` file, that file is loaded and prepended to the prompt context.
- When a named override is registered, the host can replace the generation driver, the prompt, or both without changing templates.

Runnable example:

```bash
php examples/generic/vibe_profile_fillin.php
```

The example prints JSON showing:

- file-backed profile output and prompt
- named override output and prompt

Long-form generation attributes can also compose stable thread/session identifiers directly from scoped vars.

Example:

```txt
{=content
  profile="editorial"
  label="Chapter [[chapter|pad:2]] / [[section.title]]"
  thread="book:[[book.slug|slug]]:chapter:[[chapter|pad:2]]:section:[[section.slug|slug]]"
  session="draft:[[book.slug|slug]]:[[chapter|pad:2]]"
  context_strategy="windowed-prefix"
  max_context_tokens="1200"
}
```

Supported interpolation shape:

- `[[path.to.value]]`
- `[[path.to.value|slug]]`
- `[[chapter|pad:2]]`
- `[[section.slug|default:untitled|slug]]`

`[[...]]` is the preferred form for generation attributes because it does not collide with the surrounding template tag braces.

Supported filters:

- `slug`
- `lower`
- `upper`
- `trim`
- `pad:length[:character[:left|right]]`
- `default:value`
