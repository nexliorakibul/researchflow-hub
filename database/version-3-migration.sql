-- ResearchFlow Hub - Version 3 migration
-- Run once against an existing Version 1/2 database before deploying Version 3.

USE researchflow_db;

ALTER TABLE users
    ADD COLUMN orcid_id VARCHAR(19) NULL AFTER bio;

ALTER TABLE users
    ADD UNIQUE INDEX uq_users_orcid_id (orcid_id);

ALTER TABLE papers
    ADD COLUMN code_url VARCHAR(1000) NULL AFTER url;
