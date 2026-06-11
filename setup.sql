-- Script de création des tables pour le site nordbackgammon
-- À exécuter dans la BDD "backnord" via HeidiSQL ou la console Laragon

USE backnord;

-- Table des utilisateurs (gérants et futurs membres)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

-- Table des actualités
CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT NOT NULL,
    author_id INT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id)
);

-- Table des messages de contact
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) NOT NULL DEFAULT 0
);

-- Compte admin initial : login "admin", mot de passe "changeme123"
-- IMPORTANT : changer le mot de passe après la première connexion
INSERT INTO users (username, email, password_hash, role, first_name, last_name)
VALUES (
    'admin',
    'admin@nordbackgammon.fr',
    '$2y$12$eImiTXuWVxfM37uY4JANjQ==hashed_placeholder',
    'admin',
    'Admin',
    'Nord Backgammon'
);
-- NOTE : le password_hash ci-dessus est un placeholder.
-- Exécute ce script PHP UNE FOIS pour générer le vrai hash et mettre à jour :
--   echo password_hash('changeme123', PASSWORD_BCRYPT, ['cost' => 12]);
-- Puis : UPDATE users SET password_hash = '<le_hash>' WHERE username = 'admin';
