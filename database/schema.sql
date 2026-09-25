-- ResearchFlow Hub - Version 1 database schema
-- Target: MySQL 8+
-- This file contains no credentials and is not executed during Step 4.

CREATE DATABASE IF NOT EXISTS researchflow_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE researchflow_db;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    institution VARCHAR(150) NULL,
    research_interests TEXT NULL,
    bio TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT chk_users_name_length CHECK (CHAR_LENGTH(name) BETWEEN 2 AND 100)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    research_area VARCHAR(150) NULL,
    description TEXT NULL,
    status ENUM('planned', 'ongoing', 'completed', 'archived') NOT NULL DEFAULT 'planned',
    start_date DATE NULL,
    target_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_projects_user_status (user_id, status),
    INDEX idx_projects_user_title (user_id, title),
    INDEX idx_projects_user_updated (user_id, updated_at),
    CONSTRAINT fk_projects_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_projects_dates
        CHECK (start_date IS NULL OR target_date IS NULL OR target_date >= start_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resources (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    research_area VARCHAR(150) NULL,
    authors VARCHAR(500) NULL,
    publication_year SMALLINT NULL,
    url VARCHAR(1000) NULL,
    doi VARCHAR(255) NULL,
    description TEXT NULL,
    personal_notes TEXT NULL,
    tags VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'saved',
    is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resources_user_project (user_id, project_id),
    INDEX idx_resources_filters (user_id, resource_type, status, publication_year),
    INDEX idx_resources_favorite (user_id, is_favorite),
    INDEX idx_resources_title (title),
    CONSTRAINT fk_resources_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_resources_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT chk_resources_type CHECK (resource_type IN (
        'research_paper', 'dataset', 'github_repository', 'website', 'book',
        'research_tool', 'documentation', 'pretrained_model', 'course',
        'tutorial', 'other'
    )),
    CONSTRAINT chk_resources_status
        CHECK (status IN ('saved', 'to_read', 'reading', 'completed', 'important')),
    CONSTRAINT chk_resources_year
        CHECK (publication_year IS NULL OR publication_year BETWEEN 1000 AND 9999)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS papers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    title VARCHAR(500) NOT NULL,
    authors VARCHAR(1000) NULL,
    publication_year SMALLINT NULL,
    venue VARCHAR(255) NULL,
    volume VARCHAR(50) NULL,
    issue VARCHAR(50) NULL,
    pages VARCHAR(50) NULL,
    doi VARCHAR(255) NULL,
    url VARCHAR(1000) NULL,
    research_area VARCHAR(150) NULL,
    keywords VARCHAR(1000) NULL,
    summary TEXT NULL,
    reading_status VARCHAR(30) NOT NULL DEFAULT 'to_read',
    personal_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_papers_user_project (user_id, project_id),
    INDEX idx_papers_filters (user_id, publication_year, reading_status),
    INDEX idx_papers_title (title),
    CONSTRAINT fk_papers_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_papers_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT chk_papers_reading_status
        CHECK (reading_status IN ('to_read', 'reading', 'reviewed', 'completed', 'important')),
    CONSTRAINT chk_papers_year
        CHECK (publication_year IS NULL OR publication_year BETWEEN 1000 AND 9999)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS datasets (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(150) NULL,
    source VARCHAR(255) NULL,
    url VARCHAR(1000) NULL,
    row_count BIGINT NULL,
    column_count INT NULL,
    image_count BIGINT NULL,
    class_count INT NULL,
    file_size VARCHAR(100) NULL,
    license VARCHAR(255) NULL,
    access_type VARCHAR(50) NULL,
    description TEXT NULL,
    status VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_datasets_user_project (user_id, project_id),
    INDEX idx_datasets_filters (user_id, access_type, status),
    INDEX idx_datasets_name (name),
    CONSTRAINT fk_datasets_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_datasets_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT chk_datasets_access_type
        CHECK (access_type IS NULL OR access_type IN ('public', 'restricted', 'credentialed', 'private', 'unknown')),
    CONSTRAINT chk_datasets_status
        CHECK (status IS NULL OR status IN ('identified', 'requested', 'approved', 'downloaded', 'preprocessed', 'used', 'archived')),
    CONSTRAINT chk_datasets_counts CHECK (
        (row_count IS NULL OR row_count >= 0)
        AND (column_count IS NULL OR column_count >= 0)
        AND (image_count IS NULL OR image_count >= 0)
        AND (class_count IS NULL OR class_count >= 0)
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS literature_reviews (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    paper_id BIGINT UNSIGNED NOT NULL,
    research_objective TEXT NULL,
    dataset_used VARCHAR(500) NULL,
    dataset_size VARCHAR(100) NULL,
    methodology TEXT NULL,
    models_used VARCHAR(500) NULL,
    preprocessing TEXT NULL,
    metrics VARCHAR(500) NULL,
    main_results TEXT NULL,
    key_findings TEXT NULL,
    strengths TEXT NULL,
    limitations TEXT NULL,
    future_work TEXT NULL,
    researcher_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reviews_user_project (user_id, project_id),
    INDEX idx_reviews_user_paper (user_id, paper_id),
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_paper
        FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS research_gaps (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    paper_id BIGINT UNSIGNED NULL,
    gap_title VARCHAR(255) NOT NULL,
    gap_type VARCHAR(100) NULL,
    description TEXT NOT NULL,
    evidence TEXT NULL,
    potential_solution TEXT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    status VARCHAR(30) NOT NULL DEFAULT 'identified',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gaps_user_project (user_id, project_id),
    INDEX idx_gaps_dashboard (user_id, priority, status, gap_type),
    INDEX idx_gaps_paper (paper_id),
    INDEX idx_gaps_title (gap_title),
    CONSTRAINT fk_gaps_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_gaps_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_gaps_paper
        FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE SET NULL,
    CONSTRAINT chk_gaps_type CHECK (gap_type IS NULL OR gap_type IN (
        'dataset', 'methodological', 'performance', 'validation', 'explainability',
        'multimodal', 'privacy', 'generalization', 'computational',
        'clinical_validation', 'other'
    )),
    CONSTRAINT chk_gaps_priority
        CHECK (priority IN ('low', 'medium', 'high', 'critical')),
    CONSTRAINT chk_gaps_status
        CHECK (status IN ('identified', 'investigating', 'addressed', 'rejected'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS experiments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    dataset_id BIGINT UNSIGNED NULL,
    experiment_name VARCHAR(255) NOT NULL,
    model_name VARCHAR(255) NOT NULL,
    model_type VARCHAR(100) NULL,
    preprocessing TEXT NULL,
    feature_engineering TEXT NULL,
    train_split DECIMAL(5,2) NULL,
    validation_split DECIMAL(5,2) NULL,
    test_split DECIMAL(5,2) NULL,
    random_seed INT NULL,
    learning_rate DECIMAL(12,8) NULL,
    batch_size INT NULL,
    epochs INT NULL,
    optimizer VARCHAR(100) NULL,
    loss_function VARCHAR(150) NULL,
    accuracy DECIMAL(8,6) NULL,
    precision_score DECIMAL(8,6) NULL,
    recall_score DECIMAL(8,6) NULL,
    f1_score DECIMAL(8,6) NULL,
    auroc DECIMAL(8,6) NULL,
    auprc DECIMAL(8,6) NULL,
    specificity DECIMAL(8,6) NULL,
    notes TEXT NULL,
    experiment_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_experiments_user_project (user_id, project_id),
    INDEX idx_experiments_filters (user_id, dataset_id, model_name, experiment_date),
    INDEX idx_experiments_compare (user_id, accuracy, f1_score, auroc),
    INDEX idx_experiments_name (experiment_name),
    CONSTRAINT fk_experiments_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_experiments_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_experiments_dataset
        FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE SET NULL,
    CONSTRAINT chk_experiment_splits_range CHECK (
        (train_split IS NULL OR train_split BETWEEN 0 AND 100)
        AND (validation_split IS NULL OR validation_split BETWEEN 0 AND 100)
        AND (test_split IS NULL OR test_split BETWEEN 0 AND 100)
    ),
    CONSTRAINT chk_experiment_splits_total CHECK (
        (train_split IS NULL AND validation_split IS NULL AND test_split IS NULL)
        OR (
            train_split IS NOT NULL
            AND validation_split IS NOT NULL
            AND test_split IS NOT NULL
            AND train_split + validation_split + test_split BETWEEN 99.99 AND 100.01
        )
    ),
    CONSTRAINT chk_experiment_training_values CHECK (
        (learning_rate IS NULL OR learning_rate > 0)
        AND (batch_size IS NULL OR batch_size > 0)
        AND (epochs IS NULL OR epochs > 0)
    ),
    CONSTRAINT chk_experiment_metrics CHECK (
        (accuracy IS NULL OR accuracy BETWEEN 0 AND 1)
        AND (precision_score IS NULL OR precision_score BETWEEN 0 AND 1)
        AND (recall_score IS NULL OR recall_score BETWEEN 0 AND 1)
        AND (f1_score IS NULL OR f1_score BETWEEN 0 AND 1)
        AND (auroc IS NULL OR auroc BETWEEN 0 AND 1)
        AND (auprc IS NULL OR auprc BETWEEN 0 AND 1)
        AND (specificity IS NULL OR specificity BETWEEN 0 AND 1)
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    start_date DATE NULL,
    deadline DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tasks_user_project (user_id, project_id),
    INDEX idx_tasks_dashboard (user_id, status, deadline),
    CONSTRAINT fk_tasks_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT chk_tasks_priority
        CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    CONSTRAINT chk_tasks_status
        CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled')),
    CONSTRAINT chk_tasks_dates
        CHECK (start_date IS NULL OR deadline IS NULL OR deadline >= start_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    note_type VARCHAR(50) NOT NULL DEFAULT 'general',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notes_user_project (user_id, project_id),
    INDEX idx_notes_type (user_id, note_type),
    CONSTRAINT fk_notes_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT chk_notes_type
        CHECK (note_type IN ('general', 'idea', 'meeting', 'experiment', 'paper', 'dataset', 'important'))
) ENGINE=InnoDB;
