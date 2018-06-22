DROP TABLE IF EXISTS projects;
CREATE TABLE projects (
    # Default fields
    id int(11) NOT NULL auto_increment,
    title tinytext,

    # Indices
    PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO projects (title) VALUES ('Current project'), ('Another project');