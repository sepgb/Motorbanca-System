USE motorbanca_db;
CREATE TABLE IF NOT EXISTS barangays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cluster_id INT NOT NULL,
    name VARCHAR(60) NOT NULL,
    FOREIGN KEY (cluster_id) REFERENCES clusters(id) ON DELETE CASCADE
) ENGINE = InnoDB;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE passengers;
TRUNCATE TABLE trips;
DELETE FROM users
WHERE role = 'operator';
TRUNCATE TABLE motorbancas;
TRUNCATE TABLE barangays;
TRUNCATE TABLE clusters;
SET FOREIGN_KEY_CHECKS = 1;
INSERT INTO clusters (id, name)
VALUES (1, 'Cluster 1A');
INSERT INTO barangays (cluster_id, name)
VALUES (1, 'Pipindan');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Azra', 'Not set', 40, 1);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'AZRA_operator',
        '$2y$10$vN5mCcQQRModSFdXAAmJb.NjqseYT7S4KRxu66FS8Y9Z.Tetl.83m',
        'AZRA@2405',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Brent', 'Not set', 40, 1);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'BRENT_operator',
        '$2y$10$Xs3ryzsAOw0.iErybzB1Que5cQ/0jbUhNIBhVtyeO6pnDUi/4fnvy',
        'BRENT@5824',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO clusters (id, name)
VALUES (2, 'Cluster 1B');
INSERT INTO barangays (cluster_id, name)
VALUES (2, 'Ticulyo'),
    (2, 'Navotas'),
    (2, 'Subay');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Edrilyn', 'Not set', 40, 2);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'EDRILYN_operator',
        '$2y$10$/Gnh3nYVCMt8dnnoajAj5OGRo8D3bqkSS6mX9M2rX2h1zqw8iF17.',
        'EDRILYN@4057',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Luchie', 'Not set', 40, 2);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'LUCHIE_operator',
        '$2y$10$eZcerdnZcJb75JaAqtSmp.20xBADuiPBRWdli9CPx/tqHucz6eof2',
        'LUCHIE@4314',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO clusters (id, name)
VALUES (3, 'Cluster 2A');
INSERT INTO barangays (cluster_id, name)
VALUES (3, 'Kasile'),
    (3, 'Bombong');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Ruiz', 'Not set', 40, 3);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'RUIZ_operator',
        '$2y$10$5RIWNWI3dsIKX6/ODJcV7e38.koM/qjidm8zBKk7Zd6fJxbt3AZ3K',
        'RUIZ@9519',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Emily', 'Not set', 40, 3);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'EMILY_operator',
        '$2y$10$n/I8EqQe2XYGrRjkLR9jgO.V7yIClUfdXO8UKLbrWJsiDY.L58iQq',
        'EMILY@4562',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO clusters (id, name)
VALUES (4, 'Cluster 2B');
INSERT INTO barangays (cluster_id, name)
VALUES (4, 'Kinagatan'),
    (4, 'Kinaboogan'),
    (4, 'Bangad');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Yuri', 'Not set', 40, 4);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'YURI_operator',
        '$2y$10$TkIw1EukJtl10gB5vj25QuCXkkIaWkH8CI/NgR5plzpepS7TcMwQS',
        'YURI@6538',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Helenar', 'Not set', 40, 4);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'HELENAR_operator',
        '$2y$10$rvNN7se31SKsb3OwCKclU.zTBU.IaczpZjnJthNOSuMlarj7SBpjy',
        'HELENAR@2249',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO clusters (id, name)
VALUES (5, 'Cluster 3');
INSERT INTO barangays (cluster_id, name)
VALUES (5, 'Buhangin'),
    (5, 'Janosa'),
    (5, 'Kaytome'),
    (5, 'Gulod');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Ramdem Jade', 'Not set', 40, 5);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'RAMDEMJADE_operator',
        '$2y$10$7d2tHw4GqCEd.M4jOQHiLey9EJKUJVgSmGZ7F7w1kUvYJ8g5Rd8hi',
        'RAMDEMJADE@1880',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Homer', 'Not set', 40, 5);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'HOMER_operator',
        '$2y$10$zKnEgGq/.QuD9jBLoBgm.OUoI1dX2PxYmqkLt7ucCJKYetDtRn5ny',
        'HOMER@7950',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO clusters (id, name)
VALUES (6, 'Cluster 4');
INSERT INTO barangays (cluster_id, name)
VALUES (6, 'Sapang'),
    (6, 'Malakaban');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Walastik', 'Not set', 40, 6);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'WALASTIK_operator',
        '$2y$10$8nKj2KgxNwzW2sQojX8lQuQqnQ/xCHsXKsgKQU2TAefpX2VwzzNeu',
        'WALASTIK@2488',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Maricen', 'Not set', 40, 6);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'MARICEN_operator',
        '$2y$10$acSHr1SnkBi483R5WioseOA8fMAlEUqsC.iUCbogoyc6OUBgUNpjy',
        'MARICEN@8793',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO clusters (id, name)
VALUES (7, 'Cluster 5');
INSERT INTO barangays (cluster_id, name)
VALUES (7, 'Pinagdilawan'),
    (7, 'Ginoong Sanay'),
    (7, 'Binitagan');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Dastin', 'Not set', 40, 7);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'DASTIN_operator',
        '$2y$10$A3KMTcft3bNnvPNUsCk1xu3WMgvnZL7wwb6BmZCG4JdgqUtWXOXz.',
        'DASTIN@1664',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Denz Kyle', 'Not set', 40, 7);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'DENZKYLE_operator',
        '$2y$10$eYEExgzOhkKq/C0YIifqOuR9P6SmmwhSCg6SPf3xQxbBx1XY1z4O.',
        'DENZKYLE@7049',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO clusters (id, name)
VALUES (8, 'Cluster 6');
INSERT INTO barangays (cluster_id, name)
VALUES (8, 'Tabon');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Reden', 'Not set', 40, 8);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'REDEN_operator',
        '$2y$10$6O82BY3a/g1QppHVFT6gLuA80vDscckZjEX9ajF9SuqX1IJE12/w2',
        'REDEN@3092',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO clusters (id, name)
VALUES (9, 'Cluster 7');
INSERT INTO barangays (cluster_id, name)
VALUES (9, 'Talim'),
    (9, 'Banaba');
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Houston', 'Not set', 40, 9);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'HOUSTON_operator',
        '$2y$10$KPpOUhB7m20bUfY6qvskg.Hm5cV/m6dl27rtntupzT0g7kXf4kUaO',
        'HOUSTON@5666',
        'operator',
        LAST_INSERT_ID()
    );
INSERT INTO motorbancas (
        motorbanca_name,
        owner_name,
        capacity,
        cluster_id
    )
VALUES ('M/B Califonia Express', 'Not set', 40, 9);
INSERT INTO users (
        username,
        password,
        temp_password,
        role,
        motorbanca_id
    )
VALUES (
        'CALIFONIAEXPRESS_operator',
        '$2y$10$R.YBcnvhA5WTZW.G0z8g9uwnNtycLkf.nD17D9PUXvhi/dXToPtq2',
        'CALIFONIAEXPRESS@4194',
        'operator',
        LAST_INSERT_ID()
    );