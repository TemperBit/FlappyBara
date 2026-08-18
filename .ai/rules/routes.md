---
paths:
  - routes/channels.php
---

# Routes

## Race presence supports session guests
The race presence channel must keep using the custom `race` guard. It resolves signed-in users or a session-backed GenericUser through ResolveRacePlayer, allowing invite rooms and Echo whispers to work without accounts.
