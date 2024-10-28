CREATE TABLE sys_log(
    log_data_json TEXT,
    KEY log_data_json (log_data_json(10))
);
