DROP DATABASE IF EXISTS iot_manager;
CREATE DATABASE iot_manager;
USE iot_manager;

CREATE TABLE PLCdevices (
  device_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(20) NOT NULL DEFAULT 'test',
  IP_address VARCHAR(15) NOT NULL,
  fw VARCHAR(20) NOT NULL,
  `switch` INT(2) NOT NULL,
  power FLOAT(6) NOT NULL,
  status VARCHAR(2) NOT NULL
);

CREATE TABLE roomdeployment (
  room_id INT AUTO_INCREMENT PRIMARY KEY,
  roomnoname VARCHAR(20) NOT NULL,
  bldgno VARCHAR(20) NOT NULL,
  appliances INT(2) NOT NULL,
  ipaddress VARCHAR(15) NOT NULL
);

CREATE TABLE PLCdeployment (
  deployment_id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  appliance_name VARCHAR(50) NOT NULL,
  appliance_id VARCHAR(50) NOT NULL,
  ipaddress VARCHAR(15) NOT NULL,
  power FLOAT(10) NOT NULL,
  hp FLOAT(10) NOT NULL,
  `current` FLOAT(10) NOT NULL,
  status VARCHAR(2) NOT NULL
);
