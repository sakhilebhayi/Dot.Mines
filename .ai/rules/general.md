---
paths:
  - phpstan-baseline.neon
---

# General

## Analyzer baselines only ever shrink
phpstan-baseline.neon and psalm-baseline.xml are a debt ledger being burned to zero — they may never grow. tests/Feature/AnalyzerBaselineRatchetTest.php enforces high-water marks in CI; when you reduce debt, lower the marks in the same PR. Never fix an analyzer finding by adding it to a baseline. The psalm regeneration procedure (rm + --set-baseline) is only legitimate when the result is smaller.
