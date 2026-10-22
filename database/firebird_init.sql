-- Firebird database initialization script
-- Run this after Firebird container starts to create the database and tables

-- Create database (run via isql-fb)
CREATE DATABASE 'wedding_app.fdb'
USER 'SYSDBA' PASSWORD 'masterkey'
PAGE_SIZE 4096
DEFAULT CHARACTER SET UTF8;

-- Users table
CREATE TABLE users (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address VARCHAR(500),
    avatar VARCHAR(500),
    role VARCHAR(50) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(1000),
    image VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    category_id INTEGER REFERENCES categories(id),
    name VARCHAR(255) NOT NULL,
    description VARCHAR(2000),
    short_description VARCHAR(500),
    price DOUBLE PRECISION NOT NULL DEFAULT 0,
    discount_price DOUBLE PRECISION DEFAULT 0,
    stock INTEGER DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    sku VARCHAR(100),
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Packages table
CREATE TABLE packages (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(2000),
    short_description VARCHAR(500),
    price DOUBLE PRECISION NOT NULL DEFAULT 0,
    discount_price DOUBLE PRECISION DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    features VARCHAR(2000),
    inclusions VARCHAR(2000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE orders (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    product_id INTEGER REFERENCES products(id),
    package_id INTEGER REFERENCES packages(id),
    order_number VARCHAR(100) NOT NULL UNIQUE,
    total_price DOUBLE PRECISION NOT NULL DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending',
    payment_status VARCHAR(50) DEFAULT 'unpaid',
    booking_date TIMESTAMP,
    quantity INTEGER DEFAULT 1,
    notes VARCHAR(1000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reviews table
CREATE TABLE reviews (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    product_id INTEGER REFERENCES products(id),
    package_id INTEGER REFERENCES packages(id),
    rating INTEGER NOT NULL,
    title VARCHAR(255),
    comment VARCHAR(2000),
    photos VARCHAR(2000),
    helpful_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cart table
CREATE TABLE carts (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    product_id INTEGER REFERENCES products(id),
    package_id INTEGER REFERENCES packages(id),
    quantity INTEGER NOT NULL DEFAULT 1,
    subtotal DOUBLE PRECISION DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Wishlist table
CREATE TABLE wishlists (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    product_id INTEGER REFERENCES products(id),
    package_id INTEGER REFERENCES packages(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vouchers table
CREATE TABLE vouchers (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    name VARCHAR(255),
    code VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    is_global BOOLEAN DEFAULT FALSE,
    max_uses INTEGER,
    uses_count INTEGER DEFAULT 0,
    expires_at TIMESTAMP,
    discount_amount DOUBLE PRECISION NOT NULL DEFAULT 0,
    discount_type VARCHAR(50) DEFAULT 'fixed',
    min_purchase DOUBLE PRECISION DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Media table (for CBIR images)
CREATE TABLE media (
    id GENERATOR ALWAYS AS IDENTITY(1,1) PRIMARY KEY,
    model_type VARCHAR(100) NOT NULL,
    model_id INTEGER NOT NULL,
    uuid VARCHAR(36),
    name VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    file_size BIGINT,
    file_path VARCHAR(500) NOT NULL,
    collection_name VARCHAR(100),
    custom_properties VARCHAR(1000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_number ON orders(order_number);
CREATE INDEX idx_reviews_product ON reviews(product_id);
CREATE INDEX idx_reviews_package ON reviews(package_id);
CREATE INDEX idx_carts_user ON carts(user_id);
CREATE INDEX idx_wishlists_user ON wishlists(user_id);
CREATE INDEX idx_vouchers_code ON vouchers(code);
CREATE INDEX idx_media_model ON media(model_type, model_id);

-- Trigger to update updated_at
SET TERM !! ;
CREATE TRIGGER users_updated_at FOR users
ACTIVE BEFORE UPDATE POSITION 0
AS
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
END!!
CREATE TRIGGER products_updated_at FOR products
ACTIVE BEFORE UPDATE POSITION 0
AS
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
END!!
CREATE TRIGGER orders_updated_at FOR orders
ACTIVE BEFORE UPDATE POSITION 0
AS
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
END!!
CREATE TRIGGER reviews_updated_at FOR reviews
ACTIVE BEFORE UPDATE POSITION 0
AS
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
END!!
CREATE TRIGGER vouchers_updated_at FOR vouchers
ACTIVE BEFORE UPDATE POSITION 0
AS
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
END!!
SET TERM ; !!
