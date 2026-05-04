-- database/schema.sql

DROP TABLE IF EXISTS empresa_users CASCADE;
DROP TABLE IF EXISTS empresas CASCADE;
DROP TABLE IF EXISTS users CASCADE;

CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    cpf VARCHAR(14) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    two_factor_secret VARCHAR(255),
    role VARCHAR(50) DEFAULT 'USER', -- 'USER', 'MASTER'
    reset_token VARCHAR(255),
    reset_expires_at TIMESTAMP,
    two_factor_recovery_token VARCHAR(255),
    two_factor_recovery_expires TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE empresas (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nome VARCHAR(255) NOT NULL,
    cnpj VARCHAR(18) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE empresa_users (
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    empresa_id UUID REFERENCES empresas(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'USER', -- 'ADMIN', 'USER'
    PRIMARY KEY (user_id, empresa_id)
);
