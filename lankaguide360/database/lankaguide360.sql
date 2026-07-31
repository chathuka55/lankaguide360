-- ============================================================
-- LankaGuide 360 — Full Platform Database Schema + Seed Data
-- Intelligent Tourism Booking Platform for Sri Lanka
-- Engine: MySQL 8+ / MariaDB 10.4+   Currency: LKR
--
-- HOW TO RUN:
--   phpMyAdmin (XAMPP): Import tab -> Choose this file -> Go
--   MySQL Workbench:    File > Open SQL Script -> Execute (lightning bolt)
--   Creates the `lankaguide360` database, all tables and seed data.
--   See SETUP-XAMPP.md for full steps on another computer.
--
-- DEMO LOGINS: passwords are seeded as a placeholder hash that WILL NOT verify.
-- Open  tools/generate-password-hash.php  in your browser to generate a real
-- bcrypt hash, then run the UPDATE statements it prints. See README.md.
-- ============================================================

CREATE DATABASE IF NOT EXISTS lankaguide360
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lankaguide360;

-- Drop in dependency order so the script is re-runnable.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments, trip_milestones, booking_status_history, booking_assignments,
  availability, bookings, trip_plan_services, trip_plan_choices, trip_plan_items, trip_plans, rooms, hotels, vehicles, drivers, guides,
  activities, destination_categories, destinations, priority_categories, trip_priorities,
  locations, categories, chatbot_logs, chatbot_intents, users, admin_users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------
-- Users — customers AND operational staff, one table, role-based.
-- role: customer | guide | driver | dispatcher | manager | admin
-- Guides/drivers who log in link back here via guides.user_id / drivers.user_id.
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) DEFAULT NULL,   -- NULL for pure guests
    phone VARCHAR(30) DEFAULT NULL,
    country VARCHAR(80) DEFAULT NULL,
    role ENUM('customer','guide','driver','dispatcher','manager','admin')
        NOT NULL DEFAULT 'customer',
    is_guest TINYINT(1) NOT NULL DEFAULT 0,    -- 1 = created via guest checkout
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Destination categories / travel interest tags
-- ---------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    slug VARCHAR(60) NOT NULL UNIQUE,
    icon VARCHAR(40) DEFAULT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Locations — cities/towns/hubs (Starting Location, Main Destination,
-- Cities/Towns to Visit on the trip planner)
-- ---------------------------------------------------------
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('city','town','district') NOT NULL DEFAULT 'town',
    region VARCHAR(80) NOT NULL,
    is_hub TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Trip priorities — what the visitor values most
-- ---------------------------------------------------------
CREATE TABLE trip_priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(40) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE priority_categories (
    priority_id INT NOT NULL,
    category_id INT NOT NULL,
    weight TINYINT NOT NULL DEFAULT 3,
    PRIMARY KEY (priority_id, category_id),
    FOREIGN KEY (priority_id) REFERENCES trip_priorities(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Destinations
-- ---------------------------------------------------------
CREATE TABLE destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    region VARCHAR(80) NOT NULL,
    district VARCHAR(80) NOT NULL,
    location_id INT DEFAULT NULL,
    short_description VARCHAR(255) NOT NULL,
    full_description TEXT NOT NULL,
    best_season VARCHAR(120) DEFAULT NULL,
    avg_visit_hours DECIMAL(4,1) DEFAULT 2.0,
    entry_fee_lkr DECIMAL(10,2) DEFAULT 0,
    budget_tier ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    popularity_tier ENUM('popular','hidden_gem') NOT NULL DEFAULT 'popular',
    latitude DECIMAL(9,6) DEFAULT NULL,
    longitude DECIMAL(9,6) DEFAULT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    rating DECIMAL(2,1) DEFAULT 4.0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE destination_categories (
    destination_id INT NOT NULL,
    category_id INT NOT NULL,
    weight TINYINT NOT NULL DEFAULT 3,
    PRIMARY KEY (destination_id, category_id),
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Activities linked to a destination
-- ---------------------------------------------------------
CREATE TABLE activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    duration_hours DECIMAL(4,1) DEFAULT 1.0,
    price_lkr DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Guides — some regional, some island-wide; some own a vehicle.
-- ---------------------------------------------------------
CREATE TABLE guides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,               -- login account (role='guide'), if any
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    region VARCHAR(80) NOT NULL,            -- home region, or 'Island-wide'
    languages VARCHAR(200) NOT NULL,        -- comma separated, e.g. 'English,German'
    is_regional TINYINT(1) NOT NULL DEFAULT 1,  -- 0 = national/island-wide guide
    has_own_vehicle TINYINT(1) NOT NULL DEFAULT 0,
    vehicle_id INT DEFAULT NULL,            -- their vehicle (no hard FK: set after vehicles seeded)
    daily_rate_lkr DECIMAL(10,2) NOT NULL DEFAULT 8000,
    rating DECIMAL(2,1) DEFAULT 4.5,
    bio VARCHAR(400) DEFAULT NULL,
    photo_url VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Drivers — some own a vehicle, some drive company/rented vehicles.
-- ---------------------------------------------------------
CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    region VARCHAR(80) NOT NULL,
    license_no VARCHAR(40) DEFAULT NULL,
    languages VARCHAR(200) DEFAULT 'English',
    has_own_vehicle TINYINT(1) NOT NULL DEFAULT 0,
    vehicle_id INT DEFAULT NULL,
    daily_rate_lkr DECIMAL(10,2) NOT NULL DEFAULT 5000,
    rating DECIMAL(2,1) DEFAULT 4.5,
    photo_url VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Vehicles — rentable; some come with a driver, some are self-drive.
-- owner_type/owner_ref_id is polymorphic (company/guide/driver) so no FK.
-- ---------------------------------------------------------
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,             -- e.g. 'Toyota HiAce'
    type ENUM('car','van','suv','bus','tuktuk','jeep') NOT NULL DEFAULT 'car',
    registration_no VARCHAR(30) DEFAULT NULL,
    seats INT NOT NULL DEFAULT 4,
    region VARCHAR(80) NOT NULL,
    has_driver TINYINT(1) NOT NULL DEFAULT 1,   -- 0 = self-drive rental only
    is_rentable TINYINT(1) NOT NULL DEFAULT 1,
    owner_type ENUM('company','guide','driver') NOT NULL DEFAULT 'company',
    owner_ref_id INT DEFAULT NULL,          -- guides.id or drivers.id when owned
    rate_per_day_lkr DECIMAL(10,2) NOT NULL DEFAULT 8000,
    image_url VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Hotels + rooms
-- ---------------------------------------------------------
CREATE TABLE hotels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    region VARCHAR(80) NOT NULL,
    district VARCHAR(80) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    star_rating TINYINT DEFAULT 3,
    budget_tier ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    price_per_night_lkr DECIMAL(10,2) NOT NULL DEFAULT 15000,
    amenities VARCHAR(300) DEFAULT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    latitude DECIMAL(9,6) DEFAULT NULL,
    longitude DECIMAL(9,6) DEFAULT NULL,
    rating DECIMAL(2,1) DEFAULT 4.3,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    room_type VARCHAR(80) NOT NULL,
    capacity INT NOT NULL DEFAULT 2,
    price_per_night_lkr DECIMAL(10,2) NOT NULL,
    qty_available INT NOT NULL DEFAULT 5,
    FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Trip plans generated by the recommendation engine
-- ---------------------------------------------------------
CREATE TABLE trip_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT DEFAULT NULL,            -- users.id (customer), NULL = anonymous
    plan_name VARCHAR(150) DEFAULT 'My Sri Lanka Trip',
    starting_location_id INT DEFAULT NULL,
    main_destination_id INT DEFAULT NULL,
    cities_to_visit VARCHAR(255) DEFAULT NULL,
    duration_days INT NOT NULL,
    travelers INT NOT NULL DEFAULT 2,
    budget_tier ENUM('low','medium','high') NOT NULL,
    travel_style VARCHAR(60) DEFAULT NULL,
    interests VARCHAR(255) NOT NULL,
    include_popular TINYINT(1) NOT NULL DEFAULT 1,
    include_hidden_gems TINYINT(1) NOT NULL DEFAULT 1,
    priority_id INT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (starting_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (main_destination_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (priority_id) REFERENCES trip_priorities(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE trip_plan_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_plan_id INT NOT NULL,
    destination_id INT NOT NULL,
    day_number INT NOT NULL,
    match_score DECIMAL(5,2) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (trip_plan_id) REFERENCES trip_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE trip_plan_choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_plan_id INT NOT NULL,
    choice_type VARCHAR(40) NOT NULL,
    choice_key VARCHAR(80) NOT NULL,
    choice_value VARCHAR(255) DEFAULT NULL,
    label VARCHAR(255) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_plan_id) REFERENCES trip_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE trip_plan_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_plan_id INT NOT NULL,
    service_type VARCHAR(40) NOT NULL,
    resource_id INT DEFAULT NULL,
    resource_label VARCHAR(160) DEFAULT NULL,
    price_lkr DECIMAL(10,2) DEFAULT 0,
    details VARCHAR(255) DEFAULT NULL,
    is_selected TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_plan_id) REFERENCES trip_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Bookings — the core workflow record.
-- workflow_status drives the Customer -> Dispatcher -> Manager -> Payment pipeline.
-- Resource columns (guide/driver/vehicle/hotel) are set by the dispatcher.
-- ---------------------------------------------------------
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(20) DEFAULT NULL UNIQUE,   -- human-friendly e.g. LG360-000123
    trip_plan_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,                    -- customer (registered or guest-created)
    destination_id INT DEFAULT NULL,             -- optional single-destination bookings
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    country VARCHAR(80) DEFAULT NULL,
    travel_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    party_size INT DEFAULT 1,
    notes TEXT DEFAULT NULL,
    -- resource assignment (dispatcher)
    guide_id INT DEFAULT NULL,
    driver_id INT DEFAULT NULL,
    vehicle_id INT DEFAULT NULL,
    hotel_id INT DEFAULT NULL,
    dispatcher_id INT DEFAULT NULL,
    manager_id INT DEFAULT NULL,
    performer_id INT DEFAULT NULL,               -- user delivering the service
    -- pricing (LKR)
    base_price_lkr DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_lkr DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_lkr DECIMAL(12,2) NOT NULL DEFAULT 0,
    -- workflow
    workflow_status ENUM('submitted','dispatching','assigned','manager_review',
        'approved','awaiting_payment','paid','confirmed','in_progress',
        'completed','cancelled','rejected') NOT NULL DEFAULT 'submitted',
    status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending', -- compat/quick filter
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_plan_id) REFERENCES trip_plans(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE SET NULL,
    FOREIGN KEY (guide_id) REFERENCES guides(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE SET NULL,
    FOREIGN KEY (dispatcher_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (performer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Booking status history — an audit trail of every transition.
-- ---------------------------------------------------------
CREATE TABLE booking_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    from_status VARCHAR(30) DEFAULT NULL,
    to_status VARCHAR(30) NOT NULL,
    changed_by INT DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Trip milestones — performer-reported progress on the ground.
-- ---------------------------------------------------------
CREATE TABLE trip_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    performer_id INT DEFAULT NULL,
    milestone ENUM('assigned','accepted','declined','started','picked_up',
        'arrived','completed') NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    photo_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (performer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Payments
-- ---------------------------------------------------------
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount_lkr DECIMAL(12,2) NOT NULL,
    discount_lkr DECIMAL(12,2) NOT NULL DEFAULT 0,
    method ENUM('card','bank_transfer','cash','wallet') NOT NULL DEFAULT 'card',
    status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    txn_ref VARCHAR(60) DEFAULT NULL,
    payer_name VARCHAR(120) DEFAULT NULL,
    payer_email VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Availability — simple date-range blocks per resource (polymorphic).
-- ---------------------------------------------------------
CREATE TABLE availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type ENUM('guide','driver','vehicle','hotel') NOT NULL,
    resource_id INT NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    status ENUM('available','booked','blocked') NOT NULL DEFAULT 'booked',
    booking_id INT DEFAULT NULL,
    INDEX idx_resource (resource_type, resource_id, date_from, date_to)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Chatbot knowledge base
-- ---------------------------------------------------------
CREATE TABLE chatbot_intents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intent_key VARCHAR(80) NOT NULL UNIQUE,
    keywords VARCHAR(500) NOT NULL,
    response TEXT NOT NULL,
    follow_up_action VARCHAR(120) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE chatbot_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_message TEXT NOT NULL,
    matched_intent VARCHAR(80) DEFAULT NULL,
    bot_response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA  (Sri Lankan, prices in LKR)
-- ============================================================

-- Users. password_hash is a PLACEHOLDER — generate real hashes with
-- tools/generate-password-hash.php then UPDATE these rows. See README.md.
-- Demo intended password for every account: Lanka@123
INSERT INTO users (full_name, email, password_hash, phone, country, role, is_guest) VALUES
('System Administrator','admin@lankaguide360.lk','REPLACE_WITH_GENERATED_BCRYPT_HASH','+94112000000','Sri Lanka','admin',0),      -- 1
('Manager Anushka','manager@lankaguide360.lk','REPLACE_WITH_GENERATED_BCRYPT_HASH','+94112000001','Sri Lanka','manager',0),        -- 2
('Dispatcher Dilani','dispatcher@lankaguide360.lk','REPLACE_WITH_GENERATED_BCRYPT_HASH','+94112000002','Sri Lanka','dispatcher',0),-- 3
('Nimal Perera','nimal.guide@lankaguide360.lk','REPLACE_WITH_GENERATED_BCRYPT_HASH','+94771111111','Sri Lanka','guide',0),         -- 4
('Kamala Silva','kamala.guide@lankaguide360.lk','REPLACE_WITH_GENERATED_BCRYPT_HASH','+94771111112','Sri Lanka','guide',0),        -- 5
('Sunil Rathnayake','sunil.driver@lankaguide360.lk','REPLACE_WITH_GENERATED_BCRYPT_HASH','+94772222221','Sri Lanka','driver',0),   -- 6
('Mohamed Aslam','aslam.driver@lankaguide360.lk','REPLACE_WITH_GENERATED_BCRYPT_HASH','+94772222222','Sri Lanka','driver',0),      -- 7
('Demo Traveller','customer@example.com','REPLACE_WITH_GENERATED_BCRYPT_HASH','+94770000000','United Kingdom','customer',0);        -- 8

INSERT INTO categories (name, slug, icon) VALUES
('Beaches & Coast', 'beaches', 'bi-water'),
('Wildlife & Safari', 'wildlife', 'bi-tree'),
('Culture & Heritage', 'culture', 'bi-bank'),
('Hill Country & Nature', 'hill-country', 'bi-triangle'),
('Adventure & Sports', 'adventure', 'bi-lightning'),
('Wellness & Ayurveda', 'wellness', 'bi-flower1'),
('Food & Local Life', 'food', 'bi-cup-hot'),
('Family Friendly', 'family', 'bi-people');

INSERT INTO locations (name, slug, type, region, is_hub) VALUES
('Colombo','colombo','city','Western',1),
('Negombo','negombo','city','Western',1),
('Kandy','kandy','city','Hill Country',0),
('Galle','galle','city','Southern Coast',0),
('Ella','ella-town','town','Hill Country',0),
('Nuwara Eliya','nuwara-eliya-town','city','Hill Country',0),
('Jaffna','jaffna','city','Northern',0),
('Trincomalee','trincomalee','city','East Coast',0),
('Anuradhapura','anuradhapura','city','Cultural Triangle',0),
('Polonnaruwa','polonnaruwa','city','Cultural Triangle',0),
('Matara','matara','city','Southern Coast',0),
('Batticaloa','batticaloa','city','East Coast',0),
('Sigiriya','sigiriya-town','town','Cultural Triangle',0),
('Bentota','bentota-town','town','Southern Coast',0),
('Hikkaduwa','hikkaduwa','town','Southern Coast',0),
('Arugam Bay','arugam-bay-town','town','East Coast',0),
('Dambulla','dambulla','town','Cultural Triangle',0),
('Tissamaharama','tissamaharama','town','Southern Coast',0),
('Udawalawe','udawalawe-town','town','Southern Coast',0),
('Ratnapura','ratnapura','city','Sabaragamuwa',0),
('Mirissa','mirissa-town','town','Southern Coast',0),
('Kegalle','kegalle','town','Hill Country',0);

INSERT INTO locations (name, slug, type, region, is_hub) VALUES
('Colombo District','colombo-district','district','Western',0),
('Gampaha District','gampaha-district','district','Western',0),
('Kalutara District','kalutara-district','district','Western',0),
('Kandy District','kandy-district','district','Central',0),
('Matale District','matale-district','district','Central',0),
('Nuwara Eliya District','nuwara-eliya-district','district','Central',0),
('Galle District','galle-district','district','Southern',0),
('Matara District','matara-district','district','Southern',0),
('Hambantota District','hambantota-district','district','Southern',0),
('Jaffna District','jaffna-district','district','Northern',0),
('Kilinochchi District','kilinochchi-district','district','Northern',0),
('Mannar District','mannar-district','district','Northern',0),
('Vavuniya District','vavuniya-district','district','Northern',0),
('Mullaitivu District','mullaitivu-district','district','Northern',0),
('Batticaloa District','batticaloa-district','district','Eastern',0),
('Ampara District','ampara-district','district','Eastern',0),
('Trincomalee District','trincomalee-district','district','Eastern',0),
('Kurunegala District','kurunegala-district','district','North Western',0),
('Puttalam District','puttalam-district','district','North Western',0),
('Anuradhapura District','anuradhapura-district','district','North Central',0),
('Polonnaruwa District','polonnaruwa-district','district','North Central',0),
('Badulla District','badulla-district','district','Uva',0),
('Moneragala District','moneragala-district','district','Uva',0),
('Ratnapura District','ratnapura-district','district','Sabaragamuwa',0),
('Kegalle District','kegalle-district','district','Sabaragamuwa',0);

INSERT INTO trip_priorities (name, slug, description, icon) VALUES
('Relaxation & Leisure','relaxation','Slow mornings, quiet beaches and time to unwind.','bi-cup-hot'),
('Adventure & Thrills','adventure-thrills','Hiking, surfing, safaris — get the heart rate up.','bi-lightning-charge'),
('Cultural Immersion','cultural-immersion','Temples, heritage sites and local traditions.','bi-bank'),
('Nature & Wildlife','nature-wildlife','National parks, forests and animal encounters.','bi-tree'),
('Value for Money','value-for-money','Stretch the budget further without missing the highlights.','bi-piggy-bank'),
('Time Efficiency','time-efficiency','Fewer, well-chosen stops over a lot of travel time.','bi-stopwatch');

INSERT INTO priority_categories (priority_id, category_id, weight) VALUES
(1,1,5),(1,6,5),(1,4,2),
(2,5,5),(2,2,3),(2,4,2),
(3,3,5),(3,7,3),
(4,2,5),(4,4,3),
(5,7,2),(5,8,2),
(6,3,2),(6,1,2);

INSERT INTO destinations
(name, slug, region, district, location_id, short_description, full_description, best_season, avg_visit_hours, entry_fee_lkr, budget_tier, popularity_tier, latitude, longitude, image_url, rating)
VALUES
('Sigiriya Rock Fortress','sigiriya','Cultural Triangle','Matale',13,'A 5th-century rock citadel rising from the jungle, crowned with palace ruins and famed frescoes.','Sigiriya is a UNESCO World Heritage Site built on top of a 200m granite rock. Climb past the mirror wall and lion\'s paw entrance to explore royal gardens and panoramic summit views.','Jan–Mar',3.5,4500,'medium','popular',7.957000,80.759800,NULL,4.8),
('Yala National Park','yala','Southern Coast','Hambantota',18,'Sri Lanka\'s most visited national park, with the highest leopard density in the world.','Yala spans dry monsoon forest, grassland and lagoons, home to leopards, elephants, sloth bears and over 200 bird species. Jeep safaris run at dawn and dusk.','Feb–Jun',4.0,6000,'medium','popular',6.372100,81.519600,NULL,4.7),
('Ella','ella','Hill Country','Badulla',5,'A misty highland village framed by tea estates, waterfalls and the iconic Nine Arch Bridge.','Ella is a hiker\'s base for Little Adam\'s Peak and Ella Rock, with the colonial-era train line and Nine Arch Bridge nearby. Cool climate and tea-country scenery make it a highlight of any hill-country route.','Jan–Mar, Jun–Sep',5.0,0,'low','popular',6.876700,81.046200,NULL,4.9),
('Galle Fort','galle-fort','Southern Coast','Galle',4,'A fortified colonial old town of coral-stone ramparts, boutique cafes and ocean sunsets.','Built by the Portuguese and fortified by the Dutch, Galle Fort is a living UNESCO World Heritage town with cobbled lanes, art galleries, and a rampart walk overlooking the Indian Ocean.','Dec–Apr',2.5,0,'low','popular',6.026900,80.217000,NULL,4.6),
('Mirissa Beach','mirissa','Southern Coast','Matara',21,'A crescent-shaped bay known for whale watching, surf breaks and coconut-tree sunsets.','Mirissa is the departure point for blue whale watching tours and offers a laid-back beach scene with beginner-friendly surf and fresh seafood shacks along the shore.','Nov–Apr',3.0,0,'low','popular',5.948600,80.459900,NULL,4.5),
('Nuwara Eliya','nuwara-eliya','Hill Country','Nuwara Eliya',6,'Sri Lanka\'s "Little England" — rolling tea plantations, cool climate and colonial architecture.','Set at 1,868m elevation, Nuwara Eliya is surrounded by manicured tea estates. Visit a working tea factory, stroll Victoria Park, or row on Gregory Lake in crisp mountain air.','Dec–Mar',4.0,0,'medium','popular',6.949400,80.789200,NULL,4.5),
('Temple of the Sacred Tooth Relic','kandy-temple','Cultural Triangle','Kandy',3,'A revered Buddhist shrine on Kandy Lake said to house a relic of the Buddha\'s tooth.','The Sri Dalada Maligawa is the spiritual heart of Sri Lankan Buddhism, hosting daily rituals and the spectacular Esala Perahera procession each summer.','Year-round',1.5,2000,'low','popular',7.293600,80.641200,NULL,4.7),
('Arugam Bay','arugam-bay','East Coast','Ampara',16,'A world-class right-hand point break and laid-back surf town on the untouched east coast.','Arugam Bay draws surfers for its long, consistent point break, alongside nearby lagoons, elephant-spotting safaris and a chilled beach-town social scene.','May–Sep',4.0,0,'low','hidden_gem',6.840300,81.836100,NULL,4.6),
('Ayurveda Wellness Retreat, Bentota','bentota-ayurveda','Southern Coast','Galle',14,'A riverside wellness escape offering traditional Ayurvedic treatments and yoga.','Bentota\'s wellness resorts combine centuries-old Ayurvedic therapies, herbal cuisine and yoga sessions in a tranquil river-and-ocean setting.','Nov–Apr',3.0,0,'high','hidden_gem',6.425900,79.996900,NULL,4.4),
('Pinnawala Elephant Orphanage','pinnawala','Hill Country','Kegalle',22,'A sanctuary caring for orphaned and injured elephants, with river-bathing viewings.','Established in 1975, Pinnawala is home to one of the world\'s largest herds of captive elephants, with a famous twice-daily river bathing routine open to visitors.','Year-round',2.0,3000,'medium','popular',7.302900,80.388800,NULL,4.2),
('Udawalawe National Park','udawalawe','Southern Coast','Ratnapura',19,'Open grassland plains famed for large, easily-spotted elephant herds.','Udawalawe offers some of the most reliable elephant sightings in Sri Lanka around its central reservoir, plus an Elephant Transit Home for rescued calves.','May–Sep',3.5,5500,'medium','hidden_gem',6.437400,80.898300,NULL,4.6),
('Adam\'s Peak (Sri Pada)','adams-peak','Hill Country','Ratnapura',20,'A sacred pilgrimage mountain climbed overnight to watch sunrise from its summit.','Pilgrims of many faiths climb thousands of illuminated steps through the night to reach the 2,243m summit for sunrise and the mountain\'s famous shadow.','Dec–May',6.0,0,'low','hidden_gem',6.809400,80.499200,NULL,4.8);

INSERT INTO destination_categories (destination_id, category_id, weight) VALUES
(1,3,5),(1,4,2),
(2,2,5),
(3,4,5),(3,5,4),(3,7,2),
(4,3,4),(4,7,3),(4,1,2),
(5,1,5),(5,5,3),(5,7,2),
(6,4,5),(6,6,2),
(7,3,5),(7,8,3),
(8,1,4),(8,5,5),
(9,6,5),(9,4,2),
(10,2,5),(10,8,3),
(11,2,5),(11,8,2),
(12,4,4),(12,5,4);

INSERT INTO activities (destination_id, title, description, duration_hours, price_lkr) VALUES
(1,'Sunrise summit climb','Beat the crowds and heat with an early ascent.',3.5,4500),
(2,'Morning jeep safari','4x4 leopard & elephant safari with a tracker.',4.0,9000),
(3,'Nine Arch Bridge walk','Tea-country walk to the famous viaduct.',2.0,0),
(5,'Blue whale watching','Boat tour into deep water off Mirissa.',4.0,8500),
(7,'Evening puja ceremony','Witness the drumming ritual at the temple.',1.5,2000);

-- Guides (some regional, some island-wide; some own a vehicle)
INSERT INTO guides (user_id, full_name, email, phone, region, languages, is_regional, has_own_vehicle, daily_rate_lkr, rating, bio) VALUES
(4,'Nimal Perera','nimal.guide@lankaguide360.lk','+94771111111','Hill Country','English,German',1,1,8500,4.8,'Tea-country specialist based in Ella, 12 years guiding hikers.'),
(5,'Kamala Silva','kamala.guide@lankaguide360.lk','+94771111112','Southern Coast','English,French',1,0,7500,4.7,'Galle Fort historian and coastal wildlife guide.'),
(NULL,'Ashen Fernando','ashen.guide@lankaguide360.lk','+94771111113','Cultural Triangle','English',1,0,8000,4.6,'Archaeology graduate, expert on Sigiriya & Polonnaruwa.'),
(NULL,'Tharindu Jayawardena','tharindu.guide@lankaguide360.lk','+94771111114','Island-wide','English,Japanese',0,1,9500,4.9,'National guide for multi-region tours, licensed island-wide.'),
(NULL,'Fathima Rizwan','fathima.guide@lankaguide360.lk','+94771111115','East Coast','English,Tamil',1,0,7000,4.5,'Arugam Bay surf & lagoon guide.'),
(NULL,'Ruwan Bandara','ruwan.guide@lankaguide360.lk','+94771111116','Western','English,Italian',1,0,7200,4.4,'Colombo city and Negombo lagoon guide.');

-- Drivers (some own a vehicle)
INSERT INTO drivers (user_id, full_name, email, phone, region, license_no, languages, has_own_vehicle, daily_rate_lkr, rating) VALUES
(6,'Sunil Rathnayake','sunil.driver@lankaguide360.lk','+94772222221','Hill Country','B1234567','English,Sinhala',1,6000,4.7),
(7,'Mohamed Aslam','aslam.driver@lankaguide360.lk','+94772222222','East Coast','B2345678','English,Tamil',1,5800,4.6),
(NULL,'Ravi Kumar','ravi.driver@lankaguide360.lk','+94772222223','Southern Coast','B3456789','English,Tamil',0,5000,4.5),
(NULL,'Pradeep Anthony','pradeep.driver@lankaguide360.lk','+94772222224','Western','B4567890','English,Sinhala',1,5500,4.6),
(NULL,'Nuwan Silva','nuwan.driver@lankaguide360.lk','+94772222225','Cultural Triangle','B5678901','English,Sinhala',0,5200,4.4);

-- Vehicles (rentable; some with driver, some self-drive; some owned by guide/driver)
INSERT INTO vehicles (name, type, registration_no, seats, region, has_driver, is_rentable, owner_type, owner_ref_id, rate_per_day_lkr) VALUES
('Toyota HiAce','van','WP-KA-1234',12,'Hill Country',1,1,'guide',1,12000),      -- 1 owned by guide Nimal
('Toyota KDH','van','CP-KB-2345',14,'Hill Country',1,1,'driver',1,11000),        -- 2 owned by driver Sunil
('Mitsubishi Montero','suv','EP-MO-3456',6,'East Coast',1,1,'driver',2,14000),   -- 3 owned by driver Mohamed
('Toyota Prius','car','WP-PR-4567',4,'Western',1,1,'driver',4,8000),             -- 4 owned by driver Pradeep
('Nissan Caravan','van','NC-CV-5678',10,'Cultural Triangle',1,1,'company',NULL,10000), -- 5
('Suzuki WagonR','car','SP-WR-6789',4,'Southern Coast',0,1,'company',NULL,5500), -- 6 self-drive
('Bajaj Tuk Tuk','tuktuk','SP-TK-7890',3,'Southern Coast',0,1,'company',NULL,3500), -- 7 self-drive
('Land Rover Safari Jeep','jeep','SP-JP-8901',6,'Southern Coast',1,1,'company',NULL,15000), -- 8
('Ashok Leyland Coach','bus','WP-BS-9012',30,'Western',1,1,'company',NULL,25000); -- 9

-- Link the vehicle back onto its guide/driver owner
UPDATE guides  SET vehicle_id = 1 WHERE id = 1;
UPDATE drivers SET vehicle_id = 2 WHERE id = 1;
UPDATE drivers SET vehicle_id = 3 WHERE id = 2;
UPDATE drivers SET vehicle_id = 4 WHERE id = 4;
-- Island-wide guide Tharindu owns a van too (company-listed as vehicle 5 for pooling)
UPDATE guides  SET has_own_vehicle = 1, vehicle_id = 5 WHERE id = 4;

-- Hotels
INSERT INTO hotels (name, region, district, address, star_rating, budget_tier, price_per_night_lkr, amenities, rating) VALUES
('Cinnamon Grand Colombo','Western','Colombo','77 Galle Rd, Colombo 03',5,'high',45000,'Pool,Spa,WiFi,Restaurant,Gym',4.6),
('Heritance Kandalama','Cultural Triangle','Dambulla','Kandalama, Dambulla',5,'high',38000,'Infinity Pool,Spa,WiFi,Lake View',4.7),
('98 Acres Resort','Hill Country','Ella','Greenland Estate, Ella',4,'high',32000,'Pool,WiFi,Mountain View,Restaurant',4.8),
('Jetwing Lighthouse','Southern Coast','Galle','Dadella, Galle',5,'high',40000,'Pool,Spa,WiFi,Ocean View',4.6),
('Araliya Green Hills','Hill Country','Nuwara Eliya','10 Wedderburn Rd, Nuwara Eliya',4,'medium',22000,'Pool,WiFi,Restaurant,Bar',4.4),
('Mango House Mirissa','Southern Coast','Matara','Mirissa Beach Rd',3,'medium',14000,'WiFi,Beachfront,Breakfast',4.3),
('Amaya Lake','Cultural Triangle','Sigiriya','Kandalama Rd, Dambulla',4,'medium',20000,'Pool,WiFi,Nature Trails',4.5),
('Hideaway Arugam Bay','East Coast','Ampara','Main Point Rd, Arugam Bay',2,'low',8000,'WiFi,Surf Storage,Cafe',4.2),
('Jetwing Jaffna','Northern','Jaffna','37 Mahatma Gandhi Rd, Jaffna',3,'medium',15000,'WiFi,Restaurant,Rooftop',4.3),
('Colombo City Hostel','Western','Colombo','Maradana, Colombo 10',1,'low',4500,'WiFi,Shared Kitchen,Lockers',4.0);

INSERT INTO rooms (hotel_id, room_type, capacity, price_per_night_lkr, qty_available) VALUES
(1,'Deluxe King',2,45000,10),(1,'Family Suite',4,72000,4),
(2,'Superior Room',2,38000,8),(2,'Panoramic Suite',3,60000,3),
(3,'Luxury Chalet',2,32000,6),(3,'Family Chalet',4,52000,3),
(4,'Ocean Deluxe',2,40000,8),(4,'Family Room',4,64000,3),
(5,'Standard Double',2,22000,10),(5,'Family Room',4,34000,4),
(6,'Beach Double',2,14000,6),
(7,'Lake View Double',2,20000,8),(7,'Family Cottage',4,32000,3),
(8,'Surf Cabana',2,8000,10),
(9,'Deluxe Double',2,15000,8),
(10,'Dorm Bed',1,4500,20),(10,'Private Twin',2,9000,5);

-- A couple of sample bookings to populate dashboards
INSERT INTO bookings
(reference, user_id, full_name, email, phone, country, travel_date, end_date, party_size, notes,
 base_price_lkr, total_lkr, workflow_status, status) VALUES
('LG360-000001',8,'Demo Traveller','customer@example.com','+94770000000','United Kingdom','2026-08-10','2026-08-13',2,'Honeymoon, prefer quiet hotels.',185000,185000,'submitted','pending'),
('LG360-000002',NULL,'Sofia Rossi','sofia.rossi@example.com','+39061234567','Italy','2026-09-05','2026-09-11',4,'Family with two kids, want wildlife.',420000,420000,'dispatching','pending');

INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by, note) VALUES
(1,NULL,'submitted',8,'Customer submitted booking request.'),
(2,NULL,'submitted',NULL,'Guest submitted booking request.'),
(2,'submitted','dispatching',3,'Dispatcher picked up the request.');

INSERT INTO chatbot_intents (intent_key, keywords, response, follow_up_action) VALUES
('greeting','hi,hello,hey,good morning,good evening','Ayubowan! 🙏 I\'m Bot360, your LankaGuide 360 assistant. I can help you plan a trip, find destinations, guides, drivers or vehicles. What would you like to explore?',NULL),
('hill_country','hill country,tea,nuwara eliya,ella,mountains,waterfall,train','The hill country is magical — take the scenic train to Ella for the Nine Arch Bridge and Little Adam\'s Peak, sip tea in Nuwara Eliya, and chase waterfalls. Cool climate all year!','open_trip_planner'),
('trip_planning','plan a trip,itinerary,trip planner,plan my trip,help me plan','I\'d love to help plan your trip! Head to the Trip Planner and tell me your interests, budget, travellers and days — I\'ll build a personalised day-by-day itinerary with guide, driver and vehicle suggestions.','open_trip_planner'),
('beaches','beach,beaches,surf,swimming,coast','Sri Lanka\'s coastline has something for everyone: Mirissa for whale watching, Arugam Bay for surfing, and Bentota for wellness resorts. Want me to add beaches to your trip plan?','open_trip_planner'),
('wildlife','safari,leopard,elephant,wildlife,national park','For wildlife, Yala National Park has the world\'s highest leopard density, while Udawalawe is best for guaranteed elephant sightings. Both are best on an early-morning jeep safari.',NULL),
('culture','temple,culture,heritage,history,ancient,unesco','The Cultural Triangle is unmissable — Sigiriya Rock Fortress and the Temple of the Sacred Tooth Relic in Kandy are two of the most significant heritage sites in Asia.',NULL),
('guide','guide,tour guide,local guide','We have regional and island-wide guides speaking English, German, French, Japanese and Tamil. The Trip Planner automatically suggests a suitable guide for your route.','open_trip_planner'),
('vehicle','vehicle,car,van,driver,transport,rent','We offer vans, cars, SUVs, jeeps and tuk-tuks — some with a driver, some self-drive. Your itinerary suggests a vehicle sized to your group and budget.','open_trip_planner'),
('budget','budget,cheap,cost,price,how much,lkr','Budgets vary widely: hill-country hikes can be nearly free, mid-range safaris run LKR 5,000–9,000 entry, and luxury retreats sit higher. Tell me your budget tier in the Trip Planner for a tailored LKR quote.','open_trip_planner'),
('best_time','when to visit,best time,weather,season','Sri Lanka has two monsoons, so there\'s always a good coast: Nov–Mar suits the south/west and hill country, while Apr–Sep suits the east coast (e.g. Arugam Bay).',NULL),
('booking','book,booking,reserve,reservation','Build a plan in the Trip Planner then hit "Submit booking" — our dispatcher assigns your guide, driver and vehicle, a manager confirms pricing, then you pay securely.','open_trip_planner'),
('thanks','thank you,thanks,cheers','You\'re most welcome! Ayubowan and happy travels around Sri Lanka. 🌴',NULL),
('fallback','','I\'m still learning! Could you rephrase that, or ask about destinations, trip planning, guides, vehicles, budgets or the best time to visit?',NULL);

-- ============================================================
-- End of schema + seed
-- ============================================================
