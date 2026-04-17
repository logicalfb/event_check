-- ============================================================
--  ev_att — Script de création de la base de données
--  Exécutez ce fichier une seule fois dans phpMyAdmin ou MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS event_attendance
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE event_attendance;

-- ── Table des événements ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS events (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name       VARCHAR(255)     NOT NULL,
    token      VARCHAR(32)      NOT NULL UNIQUE,
    image      VARCHAR(255)     DEFAULT NULL COMMENT 'Nom du fichier image (affiche)',
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table des participants ────────────────────────────────────
CREATE TABLE IF NOT EXISTS participants (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    event_id      INT UNSIGNED  NOT NULL,
    nom           VARCHAR(100)  NOT NULL,
    prenom        VARCHAR(100)  NOT NULL,
    check_in_time DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Contrainte d'unicité : un participant ne peut s'enregistrer qu'une fois par événement
    UNIQUE KEY uk_participant_event (event_id, nom, prenom),
    CONSTRAINT fk_participants_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
