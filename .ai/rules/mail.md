---
paths:
  - 'app/Mail/**'
---

# Mail

## Every mailable needs a render test, not just an assertQueued test
Mail::assertQueued never evaluates the views — a phantom attribute or unregistered route inside a mail template only explodes when the queue worker sends. NotificationAlertMail shipped linking a signed 'email.unsubscribe' route that did not exist (RouteNotFoundException on every send) with all pipeline tests green. tests/Feature/MailablesRenderTest.php renders every mailable; add new mailables there in the same PR.
