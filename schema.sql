-- ============================================================================
-- BSD BIM TRAINING CENTER - POSTGRESQL SCHEMA MIGRATION
-- Database: bsd2 (Matches C:\Users\BSD\Desktop\BSD\bsd employee management architecture)
-- ============================================================================

-- 1. BIM Videos Table with Target Station Assignment
CREATE TABLE IF NOT EXISTS bim_videos (
    id VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'revit',
    station VARCHAR(64) NOT NULL DEFAULT 'all', -- 'all', 'trainee', 'engineer', 'architect', 'modeler', 'manager'
    description TEXT,
    video_source VARCHAR(255) NOT NULL, -- local path, YouTube ID, or Google Drive URL
    video_type VARCHAR(32) NOT NULL DEFAULT 'local', -- 'local', 'yt', 'gdrive'
    duration VARCHAR(32) DEFAULT '15:00',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bim_videos_category ON bim_videos(category);
CREATE INDEX IF NOT EXISTS idx_bim_videos_station ON bim_videos(station);
CREATE INDEX IF NOT EXISTS idx_bim_videos_created ON bim_videos(created_at DESC);

-- 2. BIM Employee Training Progress Table
CREATE TABLE IF NOT EXISTS bim_employee_progress (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    video_id VARCHAR(64) NOT NULL REFERENCES bim_videos(id) ON DELETE CASCADE,
    percent INTEGER DEFAULT 0 CHECK (percent >= 0 AND percent <= 100),
    completed BOOLEAN DEFAULT false,
    watch_seconds INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_employee_video UNIQUE (employee_id, video_id)
);

CREATE INDEX IF NOT EXISTS idx_bim_progress_emp ON bim_employee_progress(employee_id);
CREATE INDEX IF NOT EXISTS idx_bim_progress_video ON bim_employee_progress(video_id);
CREATE INDEX IF NOT EXISTS idx_bim_progress_completed ON bim_employee_progress(completed);

-- 3. BIM Training Courses Table
CREATE TABLE IF NOT EXISTS bim_courses (
    id VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'coordination',
    station VARCHAR(64) NOT NULL DEFAULT 'all',
    level VARCHAR(32) NOT NULL DEFAULT 'Intermediate', -- Beginner, Intermediate, Advanced
    duration VARCHAR(32) DEFAULT '20 hrs',
    icon VARCHAR(32) DEFAULT '🏗️',
    description TEXT,
    syllabus JSONB DEFAULT '[]'::jsonb,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. BIM Practice Datasets Table
CREATE TABLE IF NOT EXISTS bim_practice_files (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'rvt', -- 'rvt', 'dwg', 'nwd', 'pdf'
    station VARCHAR(64) NOT NULL DEFAULT 'all',
    file_type VARCHAR(16) NOT NULL DEFAULT 'RVT',
    file_size VARCHAR(32) DEFAULT '10.0 MB',
    file_path VARCHAR(255) NOT NULL,
    icon VARCHAR(32) DEFAULT '🏗️',
    download_count INTEGER DEFAULT 0,
    uploaded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Initial seed data from current catalog with Station assignments
INSERT INTO bim_videos (id, title, category, station, description, video_source, video_type, duration)
VALUES 
    ('vid_1791639419351', 'EP 02 HOW TO LINK REVIT FROM CLOUD AR MODELING', 'revit', 'trainee', 'Direct BIM modeling demonstration for cloud linking.', 'videos/EP-02_HOW__TO_LINK_REVIT_FROM_CLOUD_AR_MODELING.mp4', 'local', '10:46'),
    ('vid_rebar_01', 'Revit Structure: X Rebar Modeling Walkthrough', 'revit', 'engineer', 'Hands-on structural modeling demonstration covering cross reinforcement, rebar layout parameters, and clash-free placement.', 'videos/X_rebar_modeling.mp4', 'local', '18:35')
ON CONFLICT (id) DO UPDATE SET station = EXCLUDED.station;
