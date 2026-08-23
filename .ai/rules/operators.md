---
paths:
  - 'app/Models/Operator*.php'
  - 'app/Services/Operators/**'
  - 'app/Support/EquipmentType.php'
  - 'app/Support/CredentialStatus.php'
  - 'app/Livewire/Operator*.php'
---

# Operators

## Operators are NOT users
An operator drives an ADT; a user signs in. Most operators never have a login, so `operators.user_id` is nullable and set-null on delete — never force a User (auth record, email, 2FA) into existence for a driver, and never delete an employment/compliance record because a login was removed.

## Compliance is computed, never stored
There is no `compliance_status` column anywhere. OperatorCompliance derives the verdict (compliant / expiring / non_compliant) from the credential rows + config/operators.php at read time, and EVERY surface (list, detail, counts, filters, future eligibility checks and alerts) must call it — a stored status or a parallel SQL reimplementation is the two-answers bug this design exists to prevent. Credential expiry semantics live once, in the ExpiresOn trait (status vocabulary in App\Support\CredentialStatus, because PHP cannot read constants off a trait). Only states a person decided are stored: qualification `standing` (suspended/revoked), medical `fitness`, training `competency`.

## Licences authorise EQUIPMENT, via one vocabulary
`operator_qualifications.equipment_type` uses App\Support\EquipmentType — the same canonical types machine matching will use. machine_type on machines is drifted free text (prod: haul_truck/other; verify: adt; the API rule still lists manufacturer names), so ALWAYS go through EquipmentType::normalise() when matching a machine to a licence; never compare raw strings. A qualification with NULL equipment_type (first aid) satisfies no machine requirement.

## Medical data is separately permission-gated
operator_medicals is health information about an identified person: read behind OperatorPolicy::viewMedical (`view_operator_medicals`), write behind manageMedical (`manage_operator_medicals`) — deliberately NOT granted to fleet_manager, only admin. The compliance summary exposes only status/expiry, never findings or restrictions text. Restrictions are surfaced for human review, not machine-blocked: `fit_with_restrictions` passes the medical gate with a "review before assignment" note.

## New permissions need the provisioning migration pattern
TeamRoleProvisioner only runs on team creation / role assignment, so catalogue additions never reach existing teams by themselves — ship a migration that re-runs provisionForTeam() for all teams (idempotent). Also: the role arrays in definitions() end with `],` not `},` — a careless sed will silently no-op (it did).

Frozen by tests/Feature/Operators/ (compliance engine + pages + permission boundaries).
