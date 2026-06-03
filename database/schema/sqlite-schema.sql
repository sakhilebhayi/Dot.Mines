CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "current_team_id" integer,
  "profile_photo_path" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "two_factor_secret" text,
  "two_factor_recovery_codes" text,
  "two_factor_confirmed_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "name" varchar not null,
  "display_name" varchar not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE INDEX "roles_team_id_index" on "roles"("team_id");
CREATE INDEX "roles_name_index" on "roles"("name");
CREATE UNIQUE INDEX "roles_name_unique" on "roles"("name");
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "team_id" integer,
  "name" varchar not null,
  "display_name" varchar not null,
  "description" text,
  "group" varchar not null default 'general',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE INDEX "permissions_team_id_index" on "permissions"("team_id");
CREATE INDEX "permissions_group_index" on "permissions"("group");
CREATE UNIQUE INDEX "permissions_team_id_name_unique" on "permissions"(
  "team_id",
  "name"
);
CREATE TABLE IF NOT EXISTS "permission_role"(
  "id" integer primary key autoincrement not null,
  "permission_id" integer not null,
  "role_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade
);
CREATE UNIQUE INDEX "permission_role_permission_id_role_id_unique" on "permission_role"(
  "permission_id",
  "role_id"
);
CREATE INDEX "permission_role_role_id_index" on "permission_role"("role_id");
CREATE TABLE IF NOT EXISTS "role_user"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "role_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade
);
CREATE UNIQUE INDEX "role_user_user_id_role_id_unique" on "role_user"(
  "user_id",
  "role_id"
);
CREATE INDEX "role_user_role_id_index" on "role_user"("role_id");
CREATE TABLE IF NOT EXISTS "machine_metrics"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "latitude" float,
  "longitude" float,
  "speed" float,
  "heading" float,
  "altitude" float,
  "engine_rpm" float,
  "engine_temperature" float,
  "coolant_temperature" float,
  "oil_pressure" float,
  "fuel_level" float,
  "fuel_consumption_rate" float,
  "throttle_position" float,
  "battery_voltage" float,
  "total_hours" float,
  "idle_hours" float,
  "load_weight" float,
  "payload_capacity_used" float,
  "tire_pressure_front_left" float,
  "tire_pressure_front_right" float,
  "tire_pressure_rear_left" float,
  "tire_pressure_rear_right" float,
  "raw_data" text,
  "created_at" datetime,
  "updated_at" datetime,
  "operating_hours" float,
  "recorded_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade
);
CREATE INDEX "machine_metrics_team_id_index" on "machine_metrics"("team_id");
CREATE INDEX "machine_metrics_machine_id_index" on "machine_metrics"(
  "machine_id"
);
CREATE INDEX "machine_metrics_created_at_index" on "machine_metrics"(
  "created_at"
);
CREATE TABLE IF NOT EXISTS "geofence_entries"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "geofence_id" integer not null,
  "machine_id" integer not null,
  "entry_time" datetime not null,
  "exit_time" datetime,
  "entry_latitude" float not null,
  "entry_longitude" float not null,
  "exit_latitude" float,
  "exit_longitude" float,
  "tonnage_loaded" float not null default '0',
  "material_type" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("geofence_id") references "geofences"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade
);
CREATE INDEX "geofence_entries_team_id_index" on "geofence_entries"("team_id");
CREATE INDEX "geofence_entries_geofence_id_index" on "geofence_entries"(
  "geofence_id"
);
CREATE INDEX "geofence_entries_machine_id_index" on "geofence_entries"(
  "machine_id"
);
CREATE INDEX "geofence_entries_geofence_id_exit_time_index" on "geofence_entries"(
  "geofence_id",
  "exit_time"
);
CREATE TABLE IF NOT EXISTS "alerts"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer,
  "type" varchar not null,
  "title" varchar not null,
  "description" text not null,
  "priority" varchar not null,
  "status" varchar not null default 'active',
  "triggered_at" datetime not null default CURRENT_TIMESTAMP,
  "acknowledged_at" datetime,
  "resolved_at" datetime,
  "acknowledged_by" integer,
  "resolved_by" integer,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  mine_area_id INTEGER NULL REFERENCES mine_areas(id) ON DELETE SET NULL,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade,
  foreign key("acknowledged_by") references "users"("id") on delete cascade,
  foreign key("resolved_by") references "users"("id") on delete cascade
);
CREATE INDEX "alerts_team_id_index" on "alerts"("team_id");
CREATE INDEX "alerts_machine_id_index" on "alerts"("machine_id");
CREATE INDEX "alerts_status_index" on "alerts"("status");
CREATE INDEX "alerts_priority_index" on "alerts"("priority");
CREATE INDEX "alerts_type_index" on "alerts"("type");
CREATE TABLE IF NOT EXISTS "reports"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "title" varchar not null,
  "type" varchar not null,
  "status" varchar not null default 'pending',
  "file_path" varchar,
  "file_size" integer,
  "format" varchar not null default 'pdf',
  "filters" text,
  "generated_by" integer,
  "generated_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("generated_by") references "users"("id") on delete cascade
);
CREATE INDEX "reports_team_id_index" on "reports"("team_id");
CREATE INDEX "reports_status_index" on "reports"("status");
CREATE INDEX "reports_type_index" on "reports"("type");
CREATE INDEX "reports_generated_by_index" on "reports"("generated_by");
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" text not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "teams"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "personal_team" tinyint(1) not null,
  "created_at" datetime,
  "updated_at" datetime,
  "email" varchar,
  "timezone" varchar not null default 'UTC',
  "language" varchar not null default 'en',
  "currency" varchar not null default 'USD',
  "active_shifts" text,
  "feed_go_live_at" datetime
);
CREATE INDEX "teams_user_id_index" on "teams"("user_id");
CREATE TABLE IF NOT EXISTS "team_user"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "user_id" integer not null,
  "role" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "team_user_team_id_user_id_unique" on "team_user"(
  "team_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "team_invitations"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "email" varchar not null,
  "role" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE UNIQUE INDEX "team_invitations_team_id_email_unique" on "team_invitations"(
  "team_id",
  "email"
);
CREATE INDEX "idx_alerts_team_status" on "alerts"("team_id", "status");
CREATE INDEX "idx_alerts_machine" on "alerts"("machine_id");
CREATE INDEX "idx_alerts_status" on "alerts"("status");
CREATE INDEX "idx_alerts_created" on "alerts"("created_at");
CREATE INDEX "idx_metrics_machine_time" on "machine_metrics"(
  "machine_id",
  "created_at"
);
CREATE INDEX "idx_metrics_team" on "machine_metrics"("team_id");
CREATE INDEX "idx_geofence_entries_machine" on "geofence_entries"(
  "machine_id"
);
CREATE INDEX "idx_geofence_entries_geofence" on "geofence_entries"(
  "geofence_id"
);
CREATE INDEX "idx_geofence_entries_entry" on "geofence_entries"("entry_time");
CREATE INDEX "idx_geofence_entries_machine_time" on "geofence_entries"(
  "machine_id",
  "entry_time"
);
CREATE INDEX "idx_reports_created" on "reports"("created_at");
CREATE INDEX "idx_users_current_team" on "users"("current_team_id");
CREATE INDEX "idx_team_user_user" on "team_user"("user_id");
CREATE INDEX "idx_team_user_team" on "team_user"("team_id");
CREATE TABLE IF NOT EXISTS "fuel_tanks"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "mine_area_id" integer,
  "name" varchar not null,
  "tank_number" varchar,
  "location_description" varchar,
  "location_latitude" numeric,
  "location_longitude" numeric,
  "capacity_liters" numeric not null,
  "current_level_liters" numeric not null default '0',
  "minimum_level_liters" numeric not null default '0',
  "fuel_type" varchar not null,
  "status" varchar check("status" in('active', 'maintenance', 'inactive', 'decommissioned')) not null default 'active',
  "last_inspection_date" date,
  "next_inspection_date" date,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "current_price_per_liter" numeric,
  "currency" varchar not null default 'ZAR',
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade
);
CREATE INDEX "fuel_tanks_team_id_index" on "fuel_tanks"("team_id");
CREATE INDEX "fuel_tanks_mine_area_id_index" on "fuel_tanks"("mine_area_id");
CREATE INDEX "fuel_tanks_status_index" on "fuel_tanks"("status");
CREATE INDEX "fuel_tanks_fuel_type_index" on "fuel_tanks"("fuel_type");
CREATE TABLE IF NOT EXISTS "fuel_consumption_metrics"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "date" date not null,
  "fuel_consumed_liters" numeric not null default '0',
  "distance_traveled_km" numeric,
  "operating_hours" numeric,
  "fuel_efficiency_lph" numeric,
  "fuel_efficiency_lpkm" numeric,
  "idle_time_hours" numeric,
  "idle_fuel_consumed" numeric,
  "average_load_percentage" numeric,
  "shift" varchar,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade
);
CREATE UNIQUE INDEX "fuel_consumption_metrics_machine_id_date_unique" on "fuel_consumption_metrics"(
  "machine_id",
  "date"
);
CREATE INDEX "fuel_consumption_metrics_team_id_index" on "fuel_consumption_metrics"(
  "team_id"
);
CREATE INDEX "fuel_consumption_metrics_machine_id_index" on "fuel_consumption_metrics"(
  "machine_id"
);
CREATE INDEX "fuel_consumption_metrics_date_index" on "fuel_consumption_metrics"(
  "date"
);
CREATE TABLE IF NOT EXISTS "fuel_alerts"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "fuel_tank_id" integer,
  "machine_id" integer,
  "alert_type" varchar check("alert_type" in('low_fuel', 'critical_fuel', 'tank_low', 'tank_critical', 'high_consumption', 'unusual_pattern', 'overdue_refill', 'leak_detected')) not null,
  "title" varchar not null,
  "message" text not null,
  "severity" varchar check("severity" in('info', 'warning', 'critical')) not null default 'warning',
  "status" varchar check("status" in('active', 'acknowledged', 'resolved')) not null default 'active',
  "triggered_at" datetime not null,
  "acknowledged_at" datetime,
  "resolved_at" datetime,
  "acknowledged_by" integer,
  "resolved_by" integer,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("fuel_tank_id") references "fuel_tanks"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade,
  foreign key("acknowledged_by") references "users"("id") on delete set null,
  foreign key("resolved_by") references "users"("id") on delete set null
);
CREATE INDEX "fuel_alerts_team_id_index" on "fuel_alerts"("team_id");
CREATE INDEX "fuel_alerts_fuel_tank_id_index" on "fuel_alerts"("fuel_tank_id");
CREATE INDEX "fuel_alerts_machine_id_index" on "fuel_alerts"("machine_id");
CREATE INDEX "fuel_alerts_alert_type_index" on "fuel_alerts"("alert_type");
CREATE INDEX "fuel_alerts_severity_index" on "fuel_alerts"("severity");
CREATE INDEX "fuel_alerts_status_index" on "fuel_alerts"("status");
CREATE INDEX "fuel_alerts_triggered_at_index" on "fuel_alerts"("triggered_at");
CREATE TABLE IF NOT EXISTS "fuel_budgets"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "mine_area_id" integer,
  "period_type" varchar not null,
  "start_date" date not null,
  "end_date" date not null,
  "budgeted_amount" numeric not null,
  "budgeted_liters" numeric,
  "actual_spent" numeric not null default '0',
  "actual_liters" numeric not null default '0',
  "status" varchar check("status" in('active', 'completed', 'exceeded')) not null default 'active',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade
);
CREATE INDEX "fuel_budgets_team_id_index" on "fuel_budgets"("team_id");
CREATE INDEX "fuel_budgets_mine_area_id_index" on "fuel_budgets"(
  "mine_area_id"
);
CREATE INDEX "fuel_budgets_period_type_index" on "fuel_budgets"("period_type");
CREATE INDEX "fuel_budgets_start_date_end_date_index" on "fuel_budgets"(
  "start_date",
  "end_date"
);
CREATE INDEX "fuel_budgets_status_index" on "fuel_budgets"("status");
CREATE TABLE IF NOT EXISTS "compliance_violations"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "violation_type" varchar not null,
  "description" text not null,
  "severity" varchar check("severity" in('critical', 'high', 'medium', 'low')) not null default 'medium',
  "detected_at" datetime not null,
  "remediation_deadline" datetime,
  "resolved_at" datetime,
  "resolved_by" integer,
  "resolution_notes" text,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("resolved_by") references "users"("id") on delete set null
);
CREATE INDEX "compliance_violations_team_id_severity_index" on "compliance_violations"(
  "team_id",
  "severity"
);
CREATE INDEX "compliance_violations_detected_at_index" on "compliance_violations"(
  "detected_at"
);
CREATE TABLE IF NOT EXISTS "machine_health_status"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "overall_health_score" integer not null default '100',
  "health_status" varchar check("health_status" in('excellent', 'good', 'fair', 'poor', 'critical')) not null default 'good',
  "component_scores" text,
  "engine_health" integer,
  "transmission_health" integer,
  "hydraulics_health" integer,
  "electrical_health" integer,
  "brakes_health" integer,
  "cooling_system_health" integer,
  "last_diagnostic_scan" datetime,
  "active_fault_codes" text,
  "fault_code_count" integer not null default '0',
  "recommendations" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade
);
CREATE UNIQUE INDEX "machine_health_status_machine_id_unique" on "machine_health_status"(
  "machine_id"
);
CREATE INDEX "machine_health_status_team_id_index" on "machine_health_status"(
  "team_id"
);
CREATE INDEX "machine_health_status_health_status_index" on "machine_health_status"(
  "health_status"
);
CREATE INDEX "machine_health_status_overall_health_score_index" on "machine_health_status"(
  "overall_health_score"
);
CREATE TABLE IF NOT EXISTS "maintenance_schedules"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "maintenance_type" varchar not null,
  "title" varchar not null,
  "description" text,
  "schedule_type" varchar check("schedule_type" in('hours', 'kilometers', 'calendar', 'condition')) not null,
  "interval_hours" integer,
  "interval_km" integer,
  "interval_days" integer,
  "last_service_hours" integer,
  "last_service_km" integer,
  "last_service_date" date,
  "next_service_hours" integer,
  "next_service_km" integer,
  "next_service_date" date,
  "priority" varchar check("priority" in('low', 'medium', 'high', 'critical')) not null default 'medium',
  "status" varchar check("status" in('active', 'due', 'overdue', 'completed', 'paused')) not null default 'active',
  "estimated_cost" numeric,
  "estimated_duration_hours" integer,
  "required_parts" text,
  "required_tools" text,
  "auto_generate_work_order" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade
);
CREATE INDEX "maintenance_schedules_team_id_index" on "maintenance_schedules"(
  "team_id"
);
CREATE INDEX "maintenance_schedules_machine_id_index" on "maintenance_schedules"(
  "machine_id"
);
CREATE INDEX "maintenance_schedules_status_index" on "maintenance_schedules"(
  "status"
);
CREATE INDEX "maintenance_schedules_next_service_date_index" on "maintenance_schedules"(
  "next_service_date"
);
CREATE TABLE IF NOT EXISTS "maintenance_records"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "maintenance_schedule_id" integer,
  "work_order_number" varchar not null,
  "maintenance_type" varchar not null,
  "title" varchar not null,
  "description" text not null,
  "work_performed" text,
  "status" varchar check("status" in('scheduled', 'in_progress', 'completed', 'cancelled')) not null default 'scheduled',
  "priority" varchar check("priority" in('low', 'medium', 'high', 'critical')) not null default 'medium',
  "scheduled_date" datetime not null,
  "started_at" datetime,
  "completed_at" datetime,
  "assigned_to" integer,
  "completed_by" integer,
  "labor_hours" numeric,
  "labor_cost" numeric,
  "parts_cost" numeric,
  "total_cost" numeric,
  "parts_used" text,
  "fault_codes_cleared" text,
  "odometer_reading" integer,
  "hour_meter_reading" integer,
  "technician_notes" text,
  "attachments" text,
  "machine_operational" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade,
  foreign key("maintenance_schedule_id") references "maintenance_schedules"("id") on delete set null,
  foreign key("assigned_to") references "users"("id") on delete set null,
  foreign key("completed_by") references "users"("id") on delete set null
);
CREATE INDEX "maintenance_records_team_id_index" on "maintenance_records"(
  "team_id"
);
CREATE INDEX "maintenance_records_machine_id_index" on "maintenance_records"(
  "machine_id"
);
CREATE INDEX "maintenance_records_status_index" on "maintenance_records"(
  "status"
);
CREATE INDEX "maintenance_records_scheduled_date_index" on "maintenance_records"(
  "scheduled_date"
);
CREATE INDEX "maintenance_records_assigned_to_index" on "maintenance_records"(
  "assigned_to"
);
CREATE INDEX "maintenance_records_work_order_number_index" on "maintenance_records"(
  "work_order_number"
);
CREATE UNIQUE INDEX "maintenance_records_work_order_number_unique" on "maintenance_records"(
  "work_order_number"
);
CREATE TABLE IF NOT EXISTS "health_metrics"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "recorded_at" datetime not null,
  "metric_type" varchar not null,
  "component" varchar not null,
  "value" numeric not null,
  "unit" varchar not null,
  "normal_min" numeric,
  "normal_max" numeric,
  "is_normal" tinyint(1) not null default '1',
  "severity" varchar check("severity" in('normal', 'warning', 'critical')) not null default 'normal',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade
);
CREATE INDEX "health_metrics_team_id_index" on "health_metrics"("team_id");
CREATE INDEX "health_metrics_machine_id_index" on "health_metrics"(
  "machine_id"
);
CREATE INDEX "health_metrics_recorded_at_index" on "health_metrics"(
  "recorded_at"
);
CREATE INDEX "health_metrics_metric_type_index" on "health_metrics"(
  "metric_type"
);
CREATE INDEX "health_metrics_severity_index" on "health_metrics"("severity");
CREATE TABLE IF NOT EXISTS "component_replacements"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "maintenance_record_id" integer,
  "component_name" varchar not null,
  "part_number" varchar,
  "manufacturer" varchar,
  "replacement_date" date not null,
  "machine_hours_at_replacement" integer,
  "machine_km_at_replacement" integer,
  "cost" numeric,
  "expected_lifespan_hours" integer,
  "expected_lifespan_km" integer,
  "replacement_reason" text,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade,
  foreign key("maintenance_record_id") references "maintenance_records"("id") on delete set null
);
CREATE INDEX "component_replacements_team_id_index" on "component_replacements"(
  "team_id"
);
CREATE INDEX "component_replacements_machine_id_index" on "component_replacements"(
  "machine_id"
);
CREATE INDEX "component_replacements_replacement_date_index" on "component_replacements"(
  "replacement_date"
);
CREATE INDEX "component_replacements_component_name_index" on "component_replacements"(
  "component_name"
);
CREATE TABLE IF NOT EXISTS "maintenance_alerts"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "maintenance_schedule_id" integer,
  "alert_type" varchar check("alert_type" in('service_due', 'service_overdue', 'health_warning', 'health_critical', 'fault_code', 'component_warning')) not null,
  "title" varchar not null,
  "message" text not null,
  "severity" varchar check("severity" in('info', 'warning', 'critical')) not null default 'warning',
  "status" varchar check("status" in('active', 'acknowledged', 'resolved')) not null default 'active',
  "triggered_at" datetime not null,
  "acknowledged_at" datetime,
  "resolved_at" datetime,
  "acknowledged_by" integer,
  "resolved_by" integer,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade,
  foreign key("maintenance_schedule_id") references "maintenance_schedules"("id") on delete set null,
  foreign key("acknowledged_by") references "users"("id") on delete set null,
  foreign key("resolved_by") references "users"("id") on delete set null
);
CREATE INDEX "maintenance_alerts_team_id_index" on "maintenance_alerts"(
  "team_id"
);
CREATE INDEX "maintenance_alerts_machine_id_index" on "maintenance_alerts"(
  "machine_id"
);
CREATE INDEX "maintenance_alerts_status_index" on "maintenance_alerts"(
  "status"
);
CREATE INDEX "maintenance_alerts_severity_index" on "maintenance_alerts"(
  "severity"
);
CREATE INDEX "maintenance_alerts_triggered_at_index" on "maintenance_alerts"(
  "triggered_at"
);
CREATE TABLE IF NOT EXISTS "integrations"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "provider" varchar not null,
  "name" varchar not null,
  "api_key" varchar not null,
  "api_secret" varchar not null,
  "webhook_url" varchar,
  "webhook_secret" varchar,
  "status" varchar not null default 'disconnected',
  "last_sync_at" datetime,
  "last_error" text,
  "machines_count" integer not null default('0'),
  "config" text,
  "created_at" datetime,
  "updated_at" datetime,
  "credentials" text,
  "last_sync_status" varchar default 'pending',
  foreign key("team_id") references teams("id") on delete cascade on update no action
);
CREATE INDEX "idx_integrations_last_sync" on "integrations"("last_sync_at");
CREATE INDEX "idx_integrations_provider" on "integrations"("provider");
CREATE INDEX "idx_integrations_team_status" on "integrations"(
  "team_id",
  "status"
);
CREATE INDEX "integrations_provider_index" on "integrations"("provider");
CREATE INDEX "integrations_status_index" on "integrations"("status");
CREATE INDEX "integrations_team_id_index" on "integrations"("team_id");
CREATE UNIQUE INDEX "integrations_team_id_provider_unique" on "integrations"(
  "team_id",
  "provider"
);
CREATE TABLE IF NOT EXISTS "iot_sensors"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "mine_area_id" integer,
  "name" varchar not null,
  "sensor_type" varchar check("sensor_type" in('temperature', 'humidity', 'dust', 'vibration', 'noise', 'air_quality', 'pressure', 'custom', 'accelerometer')) not null,
  "device_id" varchar not null,
  "status" varchar check("status" in('active', 'inactive', 'maintenance', 'online', 'offline', 'error')) not null default 'active',
  "last_reading" text,
  "last_reading_at" datetime,
  "location_latitude" numeric,
  "location_longitude" numeric,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade
);
CREATE INDEX "iot_sensors_team_id_index" on "iot_sensors"("team_id");
CREATE INDEX "iot_sensors_mine_area_id_index" on "iot_sensors"("mine_area_id");
CREATE INDEX "iot_sensors_device_id_index" on "iot_sensors"("device_id");
CREATE INDEX "iot_sensors_status_index" on "iot_sensors"("status");
CREATE UNIQUE INDEX "iot_sensors_device_id_unique" on "iot_sensors"(
  "device_id"
);
CREATE TABLE IF NOT EXISTS "sensor_readings"(
  "id" integer primary key autoincrement not null,
  "iot_sensor_id" integer not null,
  "sensor_type" varchar check("sensor_type" in('temperature', 'humidity', 'dust', 'vibration', 'noise', 'air_quality', 'pressure', 'custom')) not null,
  "value" numeric not null,
  "unit" varchar not null,
  "timestamp" datetime not null,
  "quality_score" float not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("iot_sensor_id") references "iot_sensors"("id") on delete cascade
);
CREATE INDEX "sensor_readings_iot_sensor_id_index" on "sensor_readings"(
  "iot_sensor_id"
);
CREATE INDEX "sensor_readings_timestamp_index" on "sensor_readings"(
  "timestamp"
);
CREATE TABLE IF NOT EXISTS "production_forecasts"(
  "id" integer primary key autoincrement not null,
  "mine_area_id" integer not null,
  "forecast_date" date not null,
  "material_name" varchar not null,
  "predicted_tonnage" numeric not null,
  "confidence_score" float not null default '0',
  "model_version" varchar not null default '1.0',
  "factors" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade
);
CREATE INDEX "production_forecasts_mine_area_id_index" on "production_forecasts"(
  "mine_area_id"
);
CREATE INDEX "production_forecasts_forecast_date_index" on "production_forecasts"(
  "forecast_date"
);
CREATE UNIQUE INDEX "production_forecasts_mine_area_id_forecast_date_material_name_unique" on "production_forecasts"(
  "mine_area_id",
  "forecast_date",
  "material_name"
);
CREATE TABLE IF NOT EXISTS "compliance_reports"(
  "id" integer primary key autoincrement not null,
  "mine_area_id" integer not null,
  "report_type" varchar check("report_type" in('environmental', 'safety', 'production', 'equipment', 'custom')) not null,
  "generated_by" integer not null,
  "report_date" date not null,
  "status" varchar check("status" in('draft', 'pending_review', 'approved', 'archived')) not null default 'draft',
  "data" text,
  "file_path" varchar,
  "compliance_score" float,
  "issues" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade,
  foreign key("generated_by") references "users"("id") on delete set null
);
CREATE INDEX "compliance_reports_mine_area_id_index" on "compliance_reports"(
  "mine_area_id"
);
CREATE INDEX "compliance_reports_report_type_index" on "compliance_reports"(
  "report_type"
);
CREATE INDEX "compliance_reports_status_index" on "compliance_reports"(
  "status"
);
CREATE TABLE IF NOT EXISTS "notifications"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "type" varchar check("type" in('sensor_reading', 'maintenance_alert', 'compliance_violation', 'production_anomaly', 'sensor_status_changed', 'custom')) not null,
  "title" varchar not null,
  "message" text not null,
  "alert_level" varchar check("alert_level" in('critical', 'high', 'warning', 'info')) not null default 'info',
  "data" text,
  "action_url" varchar,
  "is_read" tinyint(1) not null default '0',
  "read_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE INDEX "notifications_team_id_index" on "notifications"("team_id");
CREATE INDEX "notifications_type_index" on "notifications"("type");
CREATE INDEX "notifications_alert_level_index" on "notifications"(
  "alert_level"
);
CREATE INDEX "notifications_created_at_index" on "notifications"("created_at");
CREATE TABLE IF NOT EXISTS "notification_read"(
  "id" integer primary key autoincrement not null,
  "notification_id" integer not null,
  "user_id" integer not null,
  "read_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("notification_id") references "notifications"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "notification_read_notification_id_user_id_unique" on "notification_read"(
  "notification_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "fuel_transactions"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "fuel_tank_id" integer,
  "machine_id" integer,
  "user_id" integer not null,
  "transaction_type" varchar not null,
  "quantity_liters" numeric not null,
  "unit_price" numeric,
  "total_cost" numeric,
  "fuel_type" varchar not null,
  "transaction_date" datetime not null,
  "odometer_reading" numeric,
  "machine_hours" numeric,
  "supplier" varchar,
  "invoice_number" varchar,
  "receipt_file_path" varchar,
  "from_tank_id" integer,
  "to_tank_id" integer,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "currency" varchar not null default 'ZAR',
  "monthly_allocation_id" integer,
  foreign key("to_tank_id") references fuel_tanks("id") on delete set null on update no action,
  foreign key("from_tank_id") references fuel_tanks("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("machine_id") references machines("id") on delete cascade on update no action,
  foreign key("fuel_tank_id") references fuel_tanks("id") on delete cascade on update no action,
  foreign key("team_id") references teams("id") on delete cascade on update no action,
  foreign key("monthly_allocation_id") references "fuel_monthly_allocations"("id") on delete set null
);
CREATE INDEX "fuel_transactions_fuel_tank_id_index" on "fuel_transactions"(
  "fuel_tank_id"
);
CREATE INDEX "fuel_transactions_fuel_type_index" on "fuel_transactions"(
  "fuel_type"
);
CREATE INDEX "fuel_transactions_machine_id_index" on "fuel_transactions"(
  "machine_id"
);
CREATE INDEX "fuel_transactions_team_id_index" on "fuel_transactions"(
  "team_id"
);
CREATE INDEX "fuel_transactions_transaction_date_index" on "fuel_transactions"(
  "transaction_date"
);
CREATE INDEX "fuel_transactions_transaction_type_index" on "fuel_transactions"(
  "transaction_type"
);
CREATE INDEX "fuel_transactions_user_id_index" on "fuel_transactions"(
  "user_id"
);
CREATE TABLE IF NOT EXISTS "routes"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer,
  "mine_area_id" integer,
  "name" varchar not null,
  "description" text,
  "start_latitude" numeric not null,
  "start_longitude" numeric not null,
  "end_latitude" numeric not null,
  "end_longitude" numeric not null,
  "total_distance" numeric not null,
  "estimated_time" integer not null,
  "estimated_fuel" numeric not null,
  "route_type" varchar check("route_type" in('optimal', 'shortest', 'safest', 'custom')) not null default 'optimal',
  "status" varchar check("status" in('draft', 'active', 'archived')) not null default 'draft',
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "route_geometry" text,
  "speed_limit" integer,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete set null,
  foreign key("mine_area_id") references "mine_areas"("id") on delete set null
);
CREATE INDEX "routes_team_id_status_index" on "routes"("team_id", "status");
CREATE INDEX "routes_machine_id_index" on "routes"("machine_id");
CREATE INDEX "routes_mine_area_id_index" on "routes"("mine_area_id");
CREATE TABLE IF NOT EXISTS "waypoints"(
  "id" integer primary key autoincrement not null,
  "route_id" integer not null,
  "sequence_order" integer not null,
  "latitude" numeric not null,
  "longitude" numeric not null,
  "waypoint_type" varchar not null default 'standard',
  "name" varchar,
  "notes" text,
  "estimated_time_from_previous" integer,
  "distance_from_previous" numeric,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("route_id") references "routes"("id") on delete cascade
);
CREATE INDEX "waypoints_route_id_sequence_order_index" on "waypoints"(
  "route_id",
  "sequence_order"
);
CREATE TABLE IF NOT EXISTS "subscription_plans"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "price" numeric not null,
  "yearly_price" numeric,
  "features" text not null,
  "max_machines" integer not null default '10',
  "max_users" integer not null default '5',
  "max_geofences" integer not null default '20',
  "max_mine_areas" integer not null default '5',
  "has_advanced_analytics" tinyint(1) not null default '0',
  "has_api_access" tinyint(1) not null default '0',
  "has_priority_support" tinyint(1) not null default '0',
  "is_active" tinyint(1) not null default '1',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "paystack_plan_code" varchar,
  "paystack_yearly_plan_code" varchar
);
CREATE INDEX "subscription_plans_is_active_sort_order_index" on "subscription_plans"(
  "is_active",
  "sort_order"
);
CREATE UNIQUE INDEX "subscription_plans_slug_unique" on "subscription_plans"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "subscriptions"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "subscription_plan_id" integer not null,
  "status" varchar check("status" in('trial', 'active', 'past_due', 'canceled', 'expired')) not null default 'trial',
  "billing_cycle" varchar check("billing_cycle" in('monthly', 'yearly')) not null default 'monthly',
  "trial_ends_at" datetime,
  "current_period_start" datetime,
  "current_period_end" datetime,
  "canceled_at" datetime,
  "ends_at" datetime,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "paystack_subscription_code" varchar,
  "paystack_customer_code" varchar,
  "paystack_email_token" varchar,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("subscription_plan_id") references "subscription_plans"("id") on delete restrict
);
CREATE INDEX "subscriptions_team_id_status_index" on "subscriptions"(
  "team_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "payments"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "subscription_id" integer,
  "amount" numeric not null,
  "currency" varchar not null default 'ZAR',
  "status" varchar check("status" in('pending', 'succeeded', 'failed', 'refunded')) not null default 'pending',
  "payment_method" varchar,
  "description" text,
  "failure_reason" text,
  "paid_at" datetime,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "paystack_reference" varchar,
  "paystack_invoice_id" varchar,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("subscription_id") references "subscriptions"("id") on delete set null
);
CREATE INDEX "payments_team_id_status_index" on "payments"(
  "team_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "invoices"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "subscription_id" integer,
  "payment_id" integer,
  "invoice_number" varchar not null,
  "subtotal" numeric not null,
  "tax" numeric not null default '0',
  "total" numeric not null,
  "currency" varchar not null default 'ZAR',
  "status" varchar check("status" in('draft', 'open', 'paid', 'void', 'uncollectible')) not null default 'draft',
  "issued_at" datetime,
  "due_at" datetime,
  "paid_at" datetime,
  "pdf_url" varchar,
  "line_items" text,
  "created_at" datetime,
  "updated_at" datetime,
  "paystack_invoice_code" varchar,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("subscription_id") references "subscriptions"("id") on delete set null,
  foreign key("payment_id") references "payments"("id") on delete set null
);
CREATE INDEX "invoices_team_id_status_index" on "invoices"(
  "team_id",
  "status"
);
CREATE INDEX "invoices_invoice_number_index" on "invoices"("invoice_number");
CREATE UNIQUE INDEX "invoices_invoice_number_unique" on "invoices"(
  "invoice_number"
);
CREATE TABLE IF NOT EXISTS "usage_records"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "metric_type" varchar not null,
  "quantity" integer not null,
  "recorded_date" date not null,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE INDEX "usage_records_team_id_metric_type_recorded_date_index" on "usage_records"(
  "team_id",
  "metric_type",
  "recorded_date"
);
CREATE TABLE IF NOT EXISTS "ai_agents"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "type" varchar not null,
  "description" text,
  "status" varchar not null default 'active',
  "configuration" text,
  "capabilities" text,
  "accuracy_score" float not null default '0',
  "predictions_made" integer not null default '0',
  "successful_predictions" integer not null default '0',
  "last_trained_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "ai_agents_type_index" on "ai_agents"("type");
CREATE INDEX "ai_agents_status_index" on "ai_agents"("status");
CREATE TABLE IF NOT EXISTS "ai_recommendations"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "ai_agent_id" integer not null,
  "user_id" integer,
  "category" varchar not null,
  "priority" varchar not null,
  "status" varchar not null default 'pending',
  "title" varchar not null,
  "description" text not null,
  "data" text,
  "impact_analysis" text,
  "confidence_score" float not null default '0',
  "estimated_savings" numeric,
  "estimated_efficiency_gain" numeric,
  "related_machine_id" integer,
  "related_mine_area_id" integer,
  "related_route_id" integer,
  "implemented_at" datetime,
  "implemented_by" integer,
  "implementation_notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("ai_agent_id") references "ai_agents"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null,
  foreign key("related_machine_id") references "machines"("id") on delete set null,
  foreign key("related_mine_area_id") references "mine_areas"("id") on delete set null,
  foreign key("related_route_id") references "routes"("id") on delete set null,
  foreign key("implemented_by") references "users"("id") on delete set null
);
CREATE INDEX "ai_recommendations_team_id_status_index" on "ai_recommendations"(
  "team_id",
  "status"
);
CREATE INDEX "ai_recommendations_category_priority_index" on "ai_recommendations"(
  "category",
  "priority"
);
CREATE INDEX "ai_recommendations_created_at_index" on "ai_recommendations"(
  "created_at"
);
CREATE TABLE IF NOT EXISTS "ai_analysis_sessions"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "ai_agent_id" integer not null,
  "user_id" integer,
  "analysis_type" varchar not null,
  "status" varchar not null default 'running',
  "input_parameters" text,
  "results" text,
  "recommendations_generated" integer not null default '0',
  "processing_time_ms" integer,
  "started_at" datetime,
  "completed_at" datetime,
  "error_message" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("ai_agent_id") references "ai_agents"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "ai_analysis_sessions_team_id_status_index" on "ai_analysis_sessions"(
  "team_id",
  "status"
);
CREATE INDEX "ai_analysis_sessions_created_at_index" on "ai_analysis_sessions"(
  "created_at"
);
CREATE TABLE IF NOT EXISTS "ai_learning_data"(
  "id" integer primary key autoincrement not null,
  "ai_agent_id" integer not null,
  "team_id" integer not null,
  "recommendation_id" integer,
  "data_type" varchar not null,
  "input_data" text,
  "predicted_output" text,
  "actual_output" text,
  "accuracy" float,
  "was_accurate" tinyint(1) not null default '0',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("ai_agent_id") references "ai_agents"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("recommendation_id") references "ai_recommendations"("id") on delete cascade
);
CREATE INDEX "ai_learning_data_ai_agent_id_was_accurate_index" on "ai_learning_data"(
  "ai_agent_id",
  "was_accurate"
);
CREATE TABLE IF NOT EXISTS "ai_insights"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "insight_type" varchar not null,
  "category" varchar not null,
  "severity" varchar not null default 'info',
  "title" varchar not null,
  "description" text not null,
  "data" text,
  "visualization_data" text,
  "is_read" tinyint(1) not null default '0',
  "valid_until" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE INDEX "ai_insights_team_id_is_read_index" on "ai_insights"(
  "team_id",
  "is_read"
);
CREATE INDEX "ai_insights_category_severity_index" on "ai_insights"(
  "category",
  "severity"
);
CREATE TABLE IF NOT EXISTS "ai_predictive_alerts"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "ai_agent_id" integer not null,
  "alert_type" varchar not null,
  "severity" varchar not null,
  "title" varchar not null,
  "description" text not null,
  "predictions" text,
  "probability" float not null default '0',
  "predicted_occurrence" datetime,
  "recommended_actions" text,
  "related_machine_id" integer,
  "related_mine_area_id" integer,
  "is_acknowledged" tinyint(1) not null default '0',
  "acknowledged_by" integer,
  "acknowledged_at" datetime,
  "was_accurate" tinyint(1),
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("ai_agent_id") references "ai_agents"("id") on delete cascade,
  foreign key("related_machine_id") references "machines"("id") on delete set null,
  foreign key("related_mine_area_id") references "mine_areas"("id") on delete set null,
  foreign key("acknowledged_by") references "users"("id") on delete set null
);
CREATE INDEX "ai_predictive_alerts_team_id_is_acknowledged_index" on "ai_predictive_alerts"(
  "team_id",
  "is_acknowledged"
);
CREATE INDEX "ai_predictive_alerts_severity_predicted_occurrence_index" on "ai_predictive_alerts"(
  "severity",
  "predicted_occurrence"
);
CREATE INDEX "machine_metrics_recorded_at_index" on "machine_metrics"(
  "recorded_at"
);
CREATE TABLE IF NOT EXISTS "activity_logs"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "team_id" integer not null,
  "action" varchar not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "operator_fatigue"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "team_id" integer not null,
  "machine_id" integer,
  "shift_date" date not null,
  "shift_type" varchar not null,
  "shift_start" time not null,
  "shift_end" time not null,
  "hours_worked" numeric not null default '0',
  "consecutive_days" numeric not null default '0',
  "fatigue_score" integer not null default '0',
  "alert_level" varchar check("alert_level" in('none', 'low', 'medium', 'high', 'critical')) not null default 'none',
  "break_time_minutes" numeric not null default '0',
  "incidents_count" integer not null default '0',
  "is_rested" tinyint(1) not null default '1',
  "notes" text,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete set null
);
CREATE INDEX "operator_fatigue_team_id_shift_date_index" on "operator_fatigue"(
  "team_id",
  "shift_date"
);
CREATE INDEX "operator_fatigue_user_id_shift_date_index" on "operator_fatigue"(
  "user_id",
  "shift_date"
);
CREATE INDEX "operator_fatigue_alert_level_index" on "operator_fatigue"(
  "alert_level"
);
CREATE INDEX "operator_fatigue_fatigue_score_index" on "operator_fatigue"(
  "fatigue_score"
);
CREATE TABLE IF NOT EXISTS "production_records"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "mine_area_id" integer,
  "machine_id" integer,
  "record_date" date not null,
  "shift" varchar not null default 'day',
  "quantity_produced" numeric not null default '0',
  "unit" varchar not null default 'tonnes',
  "target_quantity" numeric,
  "notes" text,
  "status" varchar check("status" in('completed', 'in-progress', 'pending', 'paused')) not null default 'completed',
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "system_quantity" numeric,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade
);
CREATE INDEX "production_records_team_id_index" on "production_records"(
  "team_id"
);
CREATE INDEX "production_records_mine_area_id_index" on "production_records"(
  "mine_area_id"
);
CREATE INDEX "production_records_machine_id_index" on "production_records"(
  "machine_id"
);
CREATE INDEX "production_records_record_date_index" on "production_records"(
  "record_date"
);
CREATE INDEX "production_records_status_index" on "production_records"(
  "status"
);
CREATE TABLE IF NOT EXISTS "production_targets"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "mine_area_id" integer,
  "period_type" varchar not null default 'daily',
  "start_date" date not null,
  "end_date" date not null,
  "target_quantity" numeric not null,
  "unit" varchar not null default 'tonnes',
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade
);
CREATE INDEX "production_targets_team_id_index" on "production_targets"(
  "team_id"
);
CREATE INDEX "production_targets_mine_area_id_index" on "production_targets"(
  "mine_area_id"
);
CREATE INDEX "production_targets_period_type_index" on "production_targets"(
  "period_type"
);
CREATE INDEX alerts_mine_area_id_index ON alerts(mine_area_id);
CREATE TABLE IF NOT EXISTS "mine_plan_uploads"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "mine_area_id" integer not null,
  "uploaded_by" integer,
  "title" varchar not null,
  "description" text,
  "file_name" varchar not null,
  "file_path" varchar not null,
  "file_type" varchar not null,
  "file_size" integer not null default '0',
  "version" varchar not null default '1.0',
  "status" varchar check("status" in('draft', 'active', 'superseded', 'archived')) not null default 'draft',
  "effective_date" date,
  "expiry_date" date,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade,
  foreign key("uploaded_by") references "users"("id") on delete set null
);
CREATE INDEX "mine_plan_uploads_team_id_index" on "mine_plan_uploads"(
  "team_id"
);
CREATE INDEX "mine_plan_uploads_mine_area_id_index" on "mine_plan_uploads"(
  "mine_area_id"
);
CREATE INDEX "mine_plan_uploads_status_index" on "mine_plan_uploads"("status");
CREATE INDEX "mine_plan_uploads_file_type_index" on "mine_plan_uploads"(
  "file_type"
);
CREATE TABLE IF NOT EXISTS "machine_mine_area_assignments"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "mine_area_id" integer not null,
  "assigned_by" integer,
  "assigned_at" datetime not null,
  "unassigned_at" datetime,
  "reason" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete cascade,
  foreign key("assigned_by") references "users"("id") on delete set null
);
CREATE INDEX "machine_mine_area_assignments_team_id_mine_area_id_index" on "machine_mine_area_assignments"(
  "team_id",
  "mine_area_id"
);
CREATE INDEX "machine_mine_area_assignments_machine_id_mine_area_id_index" on "machine_mine_area_assignments"(
  "machine_id",
  "mine_area_id"
);
CREATE TABLE IF NOT EXISTS "geofences"(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  team_id INTEGER NOT NULL,
  mine_area_id INTEGER NULL,
  name TEXT NOT NULL,
  description TEXT NULL,
  type TEXT NOT NULL,
  coordinates TEXT NOT NULL,
  center_latitude REAL NULL,
  center_longitude REAL NULL,
  area_sqm REAL NULL,
  perimeter_m REAL NULL,
  status TEXT NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at TEXT NULL,
  updated_at TEXT NULL
);
CREATE INDEX idx_geofences_team_id ON geofences(team_id);
CREATE INDEX idx_geofences_mine_area_id ON geofences(mine_area_id);
CREATE INDEX idx_geofences_type ON geofences(type);
CREATE INDEX idx_geofences_status ON geofences(status);
CREATE TABLE IF NOT EXISTS "mine_areas"(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  team_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  description TEXT NULL,
  location TEXT NULL,
  latitude REAL NULL,
  longitude REAL NULL,
  area_size_hectares REAL NULL,
  status TEXT NOT NULL DEFAULT 'active',
  manager_name TEXT NULL,
  manager_contact TEXT NULL,
  metadata TEXT NULL,
  created_at TEXT NULL,
  updated_at TEXT NULL,
  deleted_at TEXT NULL
  ,
  "center_latitude" numeric,
  "center_longitude" numeric,
  "coordinates" text,
  "is_active" tinyint(1) not null default '1'
);
CREATE INDEX idx_mine_areas_team_id ON mine_areas(team_id);
CREATE INDEX idx_mine_areas_status ON mine_areas(status);
CREATE TABLE IF NOT EXISTS "shifts"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "shift_type" varchar,
  "started_at" datetime,
  "ended_at" datetime,
  "previous_assignments" text,
  "productivity_metrics" text,
  "performance_summary" text,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "ai_recommendation_actions"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "recommendation_hash" varchar not null,
  "recommendation" text not null,
  "status" varchar not null default 'pending',
  "actioned_by" integer,
  "actioned_at" datetime,
  "reject_reason" text,
  "performance_impact" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("actioned_by") references "users"("id") on delete set null
);
CREATE INDEX "ai_recommendation_actions_recommendation_hash_index" on "ai_recommendation_actions"(
  "recommendation_hash"
);
CREATE TABLE IF NOT EXISTS "fuel_monthly_allocations"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "year" integer not null,
  "month" integer not null,
  "allocated_liters" numeric not null,
  "fuel_price_per_liter" numeric not null,
  "total_budget_zar" numeric not null,
  "consumed_liters" numeric not null default('0'),
  "remaining_liters" numeric not null default('0'),
  "spent_zar" numeric not null default('0'),
  "remaining_budget_zar" numeric not null default('0'),
  "status" varchar not null default('planned'),
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "mine_area_id" integer,
  foreign key("team_id") references teams("id") on delete cascade on update no action,
  foreign key("mine_area_id") references "mine_areas"("id") on delete set null
);
CREATE INDEX "fuel_monthly_allocations_status_index" on "fuel_monthly_allocations"(
  "status"
);
CREATE INDEX "fuel_monthly_allocations_team_id_index" on "fuel_monthly_allocations"(
  "team_id"
);
CREATE INDEX "fuel_monthly_allocations_year_month_index" on "fuel_monthly_allocations"(
  "year",
  "month"
);
CREATE UNIQUE INDEX "fuel_monthly_allocations_team_id_mine_area_id_year_month_unique" on "fuel_monthly_allocations"(
  "team_id",
  "mine_area_id",
  "year",
  "month"
);
CREATE TABLE IF NOT EXISTS "feed_posts"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "author_id" integer not null,
  "mine_area_id" integer,
  "shift" varchar,
  "category" varchar not null,
  "priority" varchar not null default 'normal',
  "body" text not null,
  "meta" text,
  "like_count" integer not null default '0',
  "comment_count" integer not null default '0',
  "acknowledgement_count" integer not null default '0',
  "is_pinned" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("author_id") references "users"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete set null
);
CREATE INDEX "feed_posts_team_id_index" on "feed_posts"("team_id");
CREATE INDEX "feed_posts_mine_area_id_index" on "feed_posts"("mine_area_id");
CREATE INDEX "feed_posts_category_index" on "feed_posts"("category");
CREATE INDEX "feed_posts_created_at_index" on "feed_posts"("created_at");
CREATE INDEX "feed_posts_team_id_category_index" on "feed_posts"(
  "team_id",
  "category"
);
CREATE INDEX "feed_posts_team_id_created_at_index" on "feed_posts"(
  "team_id",
  "created_at"
);
CREATE INDEX "feed_posts_team_id_priority_index" on "feed_posts"(
  "team_id",
  "priority"
);
CREATE TABLE IF NOT EXISTS "feed_acknowledgements"(
  "id" integer primary key autoincrement not null,
  "post_id" integer not null,
  "user_id" integer not null,
  "acknowledged_at" datetime not null,
  foreign key("post_id") references "feed_posts"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "feed_acknowledgements_post_id_user_id_unique" on "feed_acknowledgements"(
  "post_id",
  "user_id"
);
CREATE INDEX "feed_acknowledgements_post_id_index" on "feed_acknowledgements"(
  "post_id"
);
CREATE TABLE IF NOT EXISTS "feed_comments"(
  "id" integer primary key autoincrement not null,
  "post_id" integer not null,
  "parent_comment_id" integer,
  "author_id" integer not null,
  "body" text not null,
  "is_edited" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("post_id") references "feed_posts"("id") on delete cascade,
  foreign key("parent_comment_id") references "feed_comments"("id") on delete cascade,
  foreign key("author_id") references "users"("id") on delete cascade
);
CREATE INDEX "feed_comments_post_id_index" on "feed_comments"("post_id");
CREATE INDEX "feed_comments_parent_comment_id_index" on "feed_comments"(
  "parent_comment_id"
);
CREATE TABLE IF NOT EXISTS "feed_likes"(
  "id" integer primary key autoincrement not null,
  "post_id" integer not null,
  "user_id" integer not null,
  "liked_at" datetime not null,
  foreign key("post_id") references "feed_posts"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "feed_likes_post_id_user_id_unique" on "feed_likes"(
  "post_id",
  "user_id"
);
CREATE INDEX "feed_likes_post_id_index" on "feed_likes"("post_id");
CREATE TABLE IF NOT EXISTS "feed_approvals"(
  "id" integer primary key autoincrement not null,
  "post_id" integer not null,
  "approver_id" integer not null,
  "status" varchar not null default 'pending',
  "reason" text,
  "reviewed_at" datetime,
  foreign key("post_id") references "feed_posts"("id") on delete cascade,
  foreign key("approver_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "feed_approvals_post_id_unique" on "feed_approvals"(
  "post_id"
);
CREATE INDEX "feed_approvals_post_id_status_index" on "feed_approvals"(
  "post_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "shift_templates"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "category" varchar not null,
  "title" varchar not null,
  "template_body" text not null,
  "required_fields" text,
  "created_by" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete cascade
);
CREATE INDEX "shift_templates_team_id_category_index" on "shift_templates"(
  "team_id",
  "category"
);
CREATE TABLE IF NOT EXISTS "user_feed_preferences"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "team_id" integer not null,
  "category_preferences" text,
  "notify_on_comment" tinyint(1) not null default '1',
  "notify_on_reply" tinyint(1) not null default '1',
  "notify_on_approval" tinyint(1) not null default '1',
  "notify_on_mention" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "seen_onboarding_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE UNIQUE INDEX "user_feed_preferences_user_id_team_id_unique" on "user_feed_preferences"(
  "user_id",
  "team_id"
);
CREATE TABLE IF NOT EXISTS "digest_subscriptions"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "team_id" integer not null,
  "enabled" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE UNIQUE INDEX "digest_subscriptions_user_id_team_id_unique" on "digest_subscriptions"(
  "user_id",
  "team_id"
);
CREATE TABLE IF NOT EXISTS "feed_mentions"(
  "id" integer primary key autoincrement not null,
  "mentionable_type" varchar not null,
  "mentionable_id" integer not null,
  "mentioned_user_id" integer not null,
  "mentioned_by_user_id" integer not null,
  "team_id" integer not null,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("mentioned_user_id") references "users"("id") on delete cascade,
  foreign key("mentioned_by_user_id") references "users"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE INDEX "feed_mentions_mentionable_type_mentionable_id_index" on "feed_mentions"(
  "mentionable_type",
  "mentionable_id"
);
CREATE INDEX "feed_mentions_mentioned_user_id_team_id_index" on "feed_mentions"(
  "mentioned_user_id",
  "team_id"
);
CREATE TABLE IF NOT EXISTS "feed_audit_logs"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "actor_id" integer not null,
  "action" varchar not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "meta" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  "ip_address" varchar,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("actor_id") references "users"("id") on delete cascade
);
CREATE INDEX "feed_audit_logs_team_id_created_at_index" on "feed_audit_logs"(
  "team_id",
  "created_at"
);
CREATE INDEX "feed_audit_logs_subject_type_subject_id_index" on "feed_audit_logs"(
  "subject_type",
  "subject_id"
);
CREATE TABLE IF NOT EXISTS "engine_hour_sessions"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "ignition_on_at" datetime not null,
  "ignition_off_at" datetime,
  "duration_seconds" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade
);
CREATE INDEX "engine_hour_sessions_machine_id_ignition_on_at_index" on "engine_hour_sessions"(
  "machine_id",
  "ignition_on_at"
);
CREATE INDEX "engine_hour_sessions_team_id_ignition_on_at_index" on "engine_hour_sessions"(
  "team_id",
  "ignition_on_at"
);
CREATE TABLE IF NOT EXISTS "incidents"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer,
  "mine_area_id" integer,
  "reported_by" integer,
  "resolved_by" integer,
  "category" varchar not null,
  "severity" varchar not null default 'medium',
  "title" varchar not null,
  "description" text not null,
  "occurred_at" datetime not null,
  "status" varchar not null default 'open',
  "resolution_notes" text,
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete set null,
  foreign key("mine_area_id") references "mine_areas"("id") on delete set null,
  foreign key("reported_by") references "users"("id") on delete set null,
  foreign key("resolved_by") references "users"("id") on delete set null
);
CREATE INDEX "incidents_team_id_status_index" on "incidents"(
  "team_id",
  "status"
);
CREATE INDEX "incidents_team_id_category_index" on "incidents"(
  "team_id",
  "category"
);
CREATE INDEX "incidents_team_id_severity_index" on "incidents"(
  "team_id",
  "severity"
);
CREATE INDEX "incidents_occurred_at_index" on "incidents"("occurred_at");
CREATE TABLE IF NOT EXISTS "feed_attachments"(
  "id" integer primary key autoincrement not null,
  "post_id" integer not null,
  "file_url" varchar,
  "file_type" varchar not null,
  "file_name" varchar,
  "file_size" integer,
  "uploaded_at" datetime not null,
  "storage_type" varchar not null default 'db',
  "uploader_id" integer,
  "file_data" blob,
  foreign key("post_id") references feed_posts("id") on delete cascade on update no action
);
CREATE INDEX "feed_attachments_post_id_index" on "feed_attachments"("post_id");
CREATE TABLE IF NOT EXISTS "audit_logs"(
  "id" integer primary key autoincrement not null,
  "team_id" integer,
  "actor_id" integer,
  "action" varchar not null,
  "description" text,
  "ip_address" varchar,
  "subject_type" varchar,
  "subject_id" integer,
  "meta" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE INDEX "idx_audit_team_time" on "audit_logs"("team_id", "created_at");
CREATE INDEX "idx_audit_actor_time" on "audit_logs"("actor_id", "created_at");
CREATE INDEX "idx_audit_subject" on "audit_logs"("subject_type", "subject_id");
CREATE INDEX "idx_audit_action" on "audit_logs"("action");
CREATE INDEX "idx_feed_attachments_post_uploaded" on "feed_attachments"(
  "post_id",
  "uploaded_at"
);
CREATE INDEX "idx_feed_attachments_storage" on "feed_attachments"(
  "storage_type"
);
CREATE INDEX "idx_maintenance_team_status" on "maintenance_records"(
  "team_id",
  "status"
);
CREATE INDEX "idx_users_email_verified" on "users"("email_verified_at");
CREATE INDEX "idx_feed_posts_author_time" on "feed_posts"(
  "author_id",
  "created_at"
);
CREATE INDEX "idx_feed_posts_deleted" on "feed_posts"("deleted_at");
CREATE INDEX "idx_feed_approvals_status" on "feed_approvals"("status");
CREATE INDEX "idx_feed_attachments_type" on "feed_attachments"("file_type");
CREATE INDEX "idx_audit_ip" on "audit_logs"("ip_address");
CREATE INDEX "idx_maintenance_machine_status" on "maintenance_records"(
  "machine_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "haul_dispatches"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer not null,
  "mine_area_id" integer,
  "status" varchar not null default 'idle',
  "origin_name" varchar,
  "origin_latitude" numeric,
  "origin_longitude" numeric,
  "destination_name" varchar,
  "destination_latitude" numeric,
  "destination_longitude" numeric,
  "current_latitude" numeric,
  "current_longitude" numeric,
  "current_heading" numeric,
  "current_speed_kmh" numeric not null default '0',
  "current_tonnage" numeric not null default '0',
  "current_fuel_level_litres" numeric,
  "fuel_capacity_litres" numeric,
  "total_distance_km" numeric,
  "distance_remaining_km" numeric,
  "started_at" datetime,
  "estimated_arrival_at" datetime,
  "completed_at" datetime,
  "path_coordinates" text,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete cascade,
  foreign key("mine_area_id") references "mine_areas"("id") on delete set null
);
CREATE INDEX "haul_dispatches_team_id_status_index" on "haul_dispatches"(
  "team_id",
  "status"
);
CREATE INDEX "haul_dispatches_team_id_completed_at_index" on "haul_dispatches"(
  "team_id",
  "completed_at"
);
CREATE INDEX "haul_dispatches_machine_id_status_index" on "haul_dispatches"(
  "machine_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "map_events"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "machine_id" integer,
  "mine_area_id" integer,
  "event_type" varchar not null,
  "title" varchar not null,
  "notes" text,
  "latitude" numeric,
  "longitude" numeric,
  "occurred_at" datetime not null,
  "resolved_at" datetime,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("machine_id") references "machines"("id") on delete set null,
  foreign key("mine_area_id") references "mine_areas"("id") on delete set null
);
CREATE INDEX "map_events_team_id_event_type_index" on "map_events"(
  "team_id",
  "event_type"
);
CREATE INDEX "map_events_team_id_occurred_at_index" on "map_events"(
  "team_id",
  "occurred_at"
);
CREATE INDEX "map_events_machine_id_occurred_at_index" on "map_events"(
  "machine_id",
  "occurred_at"
);
CREATE TABLE IF NOT EXISTS "machines"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "name" varchar not null,
  "machine_type" varchar not null,
  "model" varchar,
  "registration_number" varchar,
  "serial_number" varchar,
  "manufacturer_id" varchar,
  "capacity" float,
  "fuel_capacity" float,
  "hours_meter" float not null default('0'),
  "status" varchar not null default('active'),
  "last_location_latitude" float,
  "last_location_longitude" float,
  "last_location_update" datetime,
  "integration_id" integer,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "manufacturer" varchar,
  "excavator_id" integer,
  "assigned_to_excavator_at" datetime,
  "year_of_manufacture" integer,
  "cycle_time_minutes" integer,
  "queue_time_minutes" integer,
  "loading_time_minutes" integer,
  "mine_area_id" integer,
  foreign key("excavator_id") references machines("id") on delete set null on update no action,
  foreign key("integration_id") references integrations("id") on delete cascade on update no action,
  foreign key("team_id") references teams("id") on delete cascade on update no action,
  foreign key("mine_area_id") references "mine_areas"("id") on delete set null
);
CREATE INDEX "idx_machines_location" on "machines"(
  "last_location_latitude",
  "last_location_longitude"
);
CREATE INDEX "idx_machines_status" on "machines"("status");
CREATE INDEX "idx_machines_team_created" on "machines"(
  "team_id",
  "created_at"
);
CREATE INDEX "idx_machines_team_status" on "machines"("team_id", "status");
CREATE INDEX "idx_machines_type" on "machines"("machine_type");
CREATE INDEX "machines_machine_type_index" on "machines"("machine_type");
CREATE INDEX "machines_registration_number_index" on "machines"(
  "registration_number"
);
CREATE INDEX "machines_status_index" on "machines"("status");
CREATE INDEX "machines_team_id_index" on "machines"("team_id");
CREATE INDEX "machines_mine_area_id_index" on "machines"("mine_area_id");
CREATE INDEX "idx_activity_logs_team_time" on "activity_logs"(
  "team_id",
  "created_at"
);
CREATE INDEX "idx_activity_logs_user" on "activity_logs"("user_id");
CREATE UNIQUE INDEX "subscriptions_paystack_subscription_code_unique" on "subscriptions"(
  "paystack_subscription_code"
);
CREATE UNIQUE INDEX "payments_paystack_reference_unique" on "payments"(
  "paystack_reference"
);
CREATE UNIQUE INDEX "invoices_paystack_invoice_code_unique" on "invoices"(
  "paystack_invoice_code"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_01_20_000001_create_roles_table',1);
INSERT INTO migrations VALUES(5,'2026_01_20_000002_create_permissions_table',1);
INSERT INTO migrations VALUES(6,'2026_01_20_000003_create_permission_role_table',1);
INSERT INTO migrations VALUES(7,'2026_01_20_000004_create_role_user_table',1);
INSERT INTO migrations VALUES(8,'2026_01_20_000005_create_machines_table',1);
INSERT INTO migrations VALUES(9,'2026_01_20_000006_create_machine_metrics_table',1);
INSERT INTO migrations VALUES(10,'2026_01_20_000007_create_geofences_table',1);
INSERT INTO migrations VALUES(11,'2026_01_20_000008_create_geofence_entries_table',1);
INSERT INTO migrations VALUES(12,'2026_01_20_000009_create_alerts_table',1);
INSERT INTO migrations VALUES(13,'2026_01_20_000010_create_integrations_table',1);
INSERT INTO migrations VALUES(14,'2026_01_20_000011_create_reports_table',1);
INSERT INTO migrations VALUES(15,'2026_01_20_075120_add_two_factor_columns_to_users_table',1);
INSERT INTO migrations VALUES(16,'2026_01_20_075130_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(17,'2026_01_20_075130_create_teams_table',1);
INSERT INTO migrations VALUES(18,'2026_01_20_075131_create_team_user_table',1);
INSERT INTO migrations VALUES(19,'2026_01_20_075132_create_team_invitations_table',1);
INSERT INTO migrations VALUES(20,'2026_01_20_181914_add_performance_indexes',1);
INSERT INTO migrations VALUES(21,'2026_01_20_190000_create_fuel_management_tables',1);
INSERT INTO migrations VALUES(22,'2026_01_20_195622_create_compliance_violations_table',1);
INSERT INTO migrations VALUES(23,'2026_01_20_200000_create_maintenance_health_tables',1);
INSERT INTO migrations VALUES(24,'2026_01_20_add_integration_fields',1);
INSERT INTO migrations VALUES(25,'2026_01_20_create_iot_and_forecasting_tables',1);
INSERT INTO migrations VALUES(26,'2026_01_20_create_notifications_table',1);
INSERT INTO migrations VALUES(27,'2026_01_21_110952_update_machines_table_add_manufacturer_and_fix_constraints',1);
INSERT INTO migrations VALUES(28,'2026_01_21_114530_add_monthly_allocation_and_pricing_to_fuel_system',1);
INSERT INTO migrations VALUES(29,'2026_01_22_075239_add_excavator_assignment_to_machines_table',1);
INSERT INTO migrations VALUES(30,'2026_01_22_100000_create_routes_table',1);
INSERT INTO migrations VALUES(31,'2026_01_22_110000_create_subscription_tables',1);
INSERT INTO migrations VALUES(32,'2026_01_25_100000_create_ai_agents_tables',1);
INSERT INTO migrations VALUES(33,'2026_01_26_052330_add_operating_hours_and_recorded_at_to_machine_metrics_table',1);
INSERT INTO migrations VALUES(34,'2026_01_26_052626_add_year_of_manufacture_to_machines_table',1);
INSERT INTO migrations VALUES(35,'2026_01_26_081515_add_settings_to_teams_table',1);
INSERT INTO migrations VALUES(36,'2026_01_27_000001_create_activity_logs_table',1);
INSERT INTO migrations VALUES(37,'2026_02_04_102913_add_route_geometry_to_routes_table',1);
INSERT INTO migrations VALUES(38,'2026_02_06_112615_add_speed_limit_to_routes_table',1);
INSERT INTO migrations VALUES(39,'2026_02_11_000001_create_operator_fatigue_table',1);
INSERT INTO migrations VALUES(40,'2026_02_12_000000_create_mine_areas_table',1);
INSERT INTO migrations VALUES(41,'2026_02_12_000001_add_deleted_at_to_mine_areas_table',1);
INSERT INTO migrations VALUES(42,'2026_02_12_000002_create_production_tables',1);
INSERT INTO migrations VALUES(43,'2026_02_12_100000_add_mine_area_features',1);
INSERT INTO migrations VALUES(44,'2026_02_16_000000_add_location_to_mine_areas',1);
INSERT INTO migrations VALUES(45,'2026_02_16_000001_add_missing_columns_to_mine_areas',1);
INSERT INTO migrations VALUES(46,'2026_02_16_010000_make_center_coordinates_nullable',1);
INSERT INTO migrations VALUES(47,'2026_02_17_105000_add_center_columns_to_mine_areas',1);
INSERT INTO migrations VALUES(48,'2026_02_17_120000_add_coordinates_to_mine_areas',1);
INSERT INTO migrations VALUES(49,'2026_02_19_000000_create_shifts_table',1);
INSERT INTO migrations VALUES(50,'2026_02_19_000001_add_status_check_constraint_to_alerts',1);
INSERT INTO migrations VALUES(51,'2026_02_19_000001_backfill_allocation_mine_area_id',1);
INSERT INTO migrations VALUES(52,'2026_02_19_000002_create_ai_recommendation_actions_table',1);
INSERT INTO migrations VALUES(53,'2026_02_19_000004_add_mine_area_id_to_monthly_allocations',1);
INSERT INTO migrations VALUES(54,'2026_02_19_000010_make_machine_mine_area_id_not_nullable',1);
INSERT INTO migrations VALUES(55,'2026_04_07_000001_create_feed_tables',1);
INSERT INTO migrations VALUES(56,'2026_04_07_000002_create_phase2_tables',1);
INSERT INTO migrations VALUES(57,'2026_04_07_000003_create_phase4_tables',1);
INSERT INTO migrations VALUES(58,'2026_04_07_110000_add_timing_fields_to_machines_table',1);
INSERT INTO migrations VALUES(59,'2026_05_04_000001_create_engine_hour_sessions_table',1);
INSERT INTO migrations VALUES(60,'2026_05_04_000002_add_system_quantity_to_production_records',1);
INSERT INTO migrations VALUES(61,'2026_05_04_000003_create_incidents_table',1);
INSERT INTO migrations VALUES(62,'2026_05_04_000004_add_db_storage_to_feed_attachments',1);
INSERT INTO migrations VALUES(63,'2026_05_04_000005_security_hardening',1);
INSERT INTO migrations VALUES(64,'2026_05_05_000001_enterprise_security_indexes',1);
INSERT INTO migrations VALUES(65,'2026_05_06_000001_create_haul_dispatches_table',1);
INSERT INTO migrations VALUES(66,'2026_05_06_000002_create_map_events_table',1);
INSERT INTO migrations VALUES(67,'2026_06_03_185657_add_mine_area_id_to_machines_table',2);
INSERT INTO migrations VALUES(68,'2026_06_03_194115_add_indexes_to_activity_logs_table',3);
INSERT INTO migrations VALUES(69,'2026_06_03_202640_migrate_stripe_to_paystack_billing',4);
