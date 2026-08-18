---
paths:
  - config/fortify.php
---

# Config

## Keep verification route generation enabled
Keep Features::emailVerification() enabled because the scaffolded VerifyEmail.vue page imports the generated verification route module. Registration remains immediate while App\Models\User does not implement MustVerifyEmail and gameplay routes do not use the verified middleware.
