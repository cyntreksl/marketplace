---
paths:
  - 'app/{Notifications,Mail}/**'
---

# Notifications Mail

## Use the shared ProDeals transactional mail theme
Build transactional emails with Laravel Markdown MailMessage/mailables so they inherit the published ProDeals HTML and text components and the prodeals theme. Keep sender/reply-to identity in config/mail.php; do not introduce bespoke email HTML or hardcode support addresses in notification classes.
