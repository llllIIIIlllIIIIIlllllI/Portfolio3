CREATE TABLE users (
  uid INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipes (
  rid INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  description VARCHAR(300),
  type ENUM('French', 'Italian', 'Chinese', 'Indian', 'Mexican', 'Others') NOT NULL,
  cookingtime INT,
  ingredients TEXT,
  instructions TEXT,
  image VARCHAR(200),
  uid INT UNSIGNED NOT NULL,
  INDEX (uid),
  FOREIGN KEY (uid) REFERENCES users(uid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Insert sample users, all passwords are hashed via php password_hash's functionality
INSERT INTO users (username, password, email) VALUES
('steph', '$2y$10$Vv1r9LgIYP7ovpiSxqTQUuPB0pOvg2J/KQQ1bUq/bx5u66Wnsw52e', 'steph@example.com'), -- password: password123
('tom', '$2y$10$Vv1r9LgIYP7ovpiSxqTQUuPB0pOvg2J/KQQ1bUq/bx5u66Wnsw52e', 'tom@example.com'),     -- password: password123
('kyle', '$2y$10$Vv1r9LgIYP7ovpiSxqTQUuPB0pOvg2J/KQQ1bUq/bx5u66Wnsw52e', 'kyle@example.com'); -- password: password123

INSERT INTO recipes (name, description, type, cookingtime, ingredients, instructions, image, uid) VALUES
('Classic French Omelette', 
 'A simple and classic French omelette recipe.',
 'French', 
 10, 
 '3 eggs, butter, salt, pepper', 
 'Beat eggs, melt butter in pan, cook eggs gently, fold omelette, serve.', 
 NULL, 
 1),

('Spaghetti Carbonara', 
 'Traditional Italian pasta with eggs, cheese, pancetta, and pepper.', 
 'Italian', 
 25, 
 'Spaghetti, eggs, pancetta, Parmesan cheese, black pepper, salt', 
 'Cook spaghetti. Fry pancetta. Mix eggs and cheese. Combine all with pepper.', 
 NULL, 
 2),

('Kung Pao Chicken', 
 'Spicy, stir-fried Chinese chicken dish with peanuts.', 
 'Chinese', 
 30, 
 'Chicken, peanuts, chili peppers, soy sauce, garlic, ginger, green onions', 
 'Marinate chicken. Stir-fry with ingredients and sauce. Add peanuts.', 
 NULL, 
 3);
