CREATE TABLE comment (
    id INT AUTO_INCREMENT NOT NULL,
    owner_id INT DEFAULT NULL,
    resource_id INT DEFAULT NULL,
    site_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    path VARCHAR(1024) NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(190) NOT NULL,
    website VARCHAR(760) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    body LONGTEXT NOT NULL,
    approved TINYINT(1) NOT NULL,
    flagged TINYINT(1) NOT NULL,
    spam TINYINT(1) NOT NULL,
    created DATETIME NOT NULL,
    modified DATETIME DEFAULT NULL,
    INDEX IDX_9474526C7E3C61F9 (owner_id),
    INDEX IDX_9474526C89329D25 (resource_id),
    INDEX IDX_9474526CF6BD1646 (site_id),
    INDEX IDX_9474526C727ACA70 (parent_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE comment ADD CONSTRAINT FK_9474526C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL;
ALTER TABLE comment ADD CONSTRAINT FK_9474526C89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE SET NULL;
ALTER TABLE comment ADD CONSTRAINT FK_9474526CF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE SET NULL;
ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id) ON DELETE SET NULL;