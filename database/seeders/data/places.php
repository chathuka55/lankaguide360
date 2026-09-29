<?php

/*
 * Starter list of well-known Sri Lankan places (phase 2). All rows are seeded as drafts
 * with no coordinates, description or fees: the Wikipedia importer (phase 3) fills text and
 * lat/lng from `wikipedia` (the exact English Wikipedia article title), and admins enter
 * fees and opening hours before publishing.
 *
 * Columns: name, wikipedia title, district, categories, visit minutes, best time slot, crowd level.
 * Category keys: beach, history, hills, wildlife, hiking, gems (see PlaceSeeder::CATEGORY_KEYS).
 * A place in "gems" is flagged is_hidden_gem.
 */
return [
    // Galle
    ['Galle Fort', 'Galle Fort', 'Galle', ['history'], 150, 'sunset', 'high'],
    ['Unawatuna Beach', 'Unawatuna', 'Galle', ['beach'], 180, 'any', 'high'],
    ['Hikkaduwa Beach', 'Hikkaduwa', 'Galle', ['beach'], 180, 'any', 'high'],
    ['Bentota Beach', 'Bentota', 'Galle', ['beach'], 180, 'any', 'medium'],
    ['Koggala', 'Koggala', 'Galle', ['beach'], 120, 'afternoon', 'medium'],
    ['Brief Garden', 'Brief (garden)', 'Galle', ['history'], 90, 'morning', 'low'],

    // Matara
    ['Mirissa Beach', 'Mirissa', 'Matara', ['beach'], 180, 'any', 'high'],
    ['Weligama Bay', 'Weligama', 'Matara', ['beach'], 150, 'any', 'medium'],
    ['Dondra Head Lighthouse', 'Dondra Head Lighthouse', 'Matara', ['beach'], 60, 'afternoon', 'low'],

    // Hambantota
    ['Yala National Park', 'Yala National Park', 'Hambantota', ['wildlife'], 240, 'sunrise', 'high'],
    ['Bundala National Park', 'Bundala National Park', 'Hambantota', ['wildlife'], 180, 'morning', 'low'],
    ['Tangalle Beach', 'Tangalle', 'Hambantota', ['beach'], 150, 'any', 'low'],
    ['Ussangoda National Park', 'Ussangoda National Park', 'Hambantota', ['wildlife'], 90, 'sunset', 'low'],

    // Kalutara
    ['Beruwala Beach', 'Beruwala', 'Kalutara', ['beach'], 150, 'any', 'medium'],
    ['Wadduwa Beach', 'Wadduwa', 'Kalutara', ['beach'], 120, 'any', 'low'],

    // Gampaha
    ['Negombo Beach', 'Negombo', 'Gampaha', ['beach'], 150, 'any', 'high'],
    ['Negombo Lagoon', 'Negombo Lagoon', 'Gampaha', ['beach'], 90, 'morning', 'medium'],

    // Trincomalee
    ['Nilaveli Beach', 'Nilaveli', 'Trincomalee', ['beach'], 180, 'any', 'medium'],
    ['Uppuveli Beach', 'Uppuveli', 'Trincomalee', ['beach'], 150, 'any', 'medium'],
    ['Pigeon Island National Park', 'Pigeon Island National Park', 'Trincomalee', ['wildlife', 'beach'], 180, 'morning', 'high'],
    ['Koneswaram Temple', 'Koneswaram Temple', 'Trincomalee', ['history'], 60, 'morning', 'medium'],

    // Batticaloa
    ['Pasikudah Beach', 'Pasikudah', 'Batticaloa', ['beach'], 180, 'any', 'medium'],
    ['Kalkudah Beach', 'Kalkudah', 'Batticaloa', ['beach'], 150, 'any', 'low'],

    // Ampara
    ['Arugam Bay', 'Arugam Bay', 'Ampara', ['beach'], 240, 'any', 'medium'],
    ['Gal Oya National Park', 'Gal Oya National Park', 'Ampara', ['gems'], 240, 'morning', 'low'],
    ['Kumana National Park', 'Kumana National Park', 'Ampara', ['gems'], 240, 'sunrise', 'low'],
    ['Lahugala Kitulana National Park', 'Lahugala Kitulana National Park', 'Ampara', ['gems'], 120, 'afternoon', 'low'],
    ['Deegavapi', 'Deegavapi', 'Ampara', ['gems'], 90, 'morning', 'low'],
    ['Muhudu Maha Viharaya', 'Muhudu Maha Viharaya', 'Ampara', ['gems'], 60, 'sunset', 'low'],

    // Puttalam
    ['Kalpitiya', 'Kalpitiya', 'Puttalam', ['beach'], 180, 'morning', 'low'],
    ['Wilpattu National Park', 'Wilpattu National Park', 'Puttalam', ['wildlife'], 300, 'sunrise', 'medium'],
    ['Anawilundawa Wetland Sanctuary', 'Anawilundawa Wetland Sanctuary', 'Puttalam', ['wildlife'], 120, 'morning', 'low'],

    // Anuradhapura
    ['Jaya Sri Maha Bodhi', 'Jaya Sri Maha Bodhi', 'Anuradhapura', ['history'], 60, 'morning', 'high'],
    ['Ruwanwelisaya', 'Ruwanwelisaya', 'Anuradhapura', ['history'], 60, 'sunset', 'high'],
    ['Jetavanaramaya', 'Jetavanaramaya', 'Anuradhapura', ['history'], 60, 'any', 'medium'],
    ['Abhayagiri Vihara', 'Abhayagiri vihāra', 'Anuradhapura', ['history'], 90, 'any', 'medium'],
    ['Thuparamaya', 'Thuparamaya', 'Anuradhapura', ['history'], 45, 'any', 'medium'],
    ['Isurumuniya', 'Isurumuniya', 'Anuradhapura', ['history'], 45, 'any', 'medium'],
    ['Mihintale', 'Mihintale', 'Anuradhapura', ['history'], 120, 'morning', 'medium'],
    ['Ritigala', 'Ritigala', 'Anuradhapura', ['gems', 'history'], 150, 'morning', 'low'],
    ['Avukana Buddha Statue', 'Avukana Buddha statue', 'Anuradhapura', ['gems', 'history'], 60, 'morning', 'low'],
    ['Kuttam Pokuna', 'Kuttam Pokuna', 'Anuradhapura', ['history'], 30, 'any', 'low'],
    ['Samadhi Statue', 'Samadhi Statue', 'Anuradhapura', ['history'], 30, 'any', 'medium'],
    ['Lovamahapaya', 'Lovamahapaya', 'Anuradhapura', ['history'], 20, 'any', 'low'],
    ['Mirisawetiya Vihara', 'Mirisawetiya Vihara', 'Anuradhapura', ['history'], 30, 'any', 'low'],

    // Polonnaruwa
    ['Ancient City of Polonnaruwa', 'Ancient City of Polonnaruwa', 'Polonnaruwa', ['history'], 240, 'morning', 'high'],
    ['Gal Vihara', 'Gal Vihara', 'Polonnaruwa', ['history'], 60, 'morning', 'high'],
    ['Polonnaruwa Vatadage', 'Polonnaruwa Vatadage', 'Polonnaruwa', ['history'], 30, 'morning', 'medium'],
    ['Rankoth Vehera', 'Rankoth Vehera', 'Polonnaruwa', ['history'], 30, 'any', 'medium'],
    ['Parakrama Samudra', 'Parakrama Samudra', 'Polonnaruwa', ['history'], 45, 'sunset', 'low'],
    ['Dimbulagala', 'Dimbulagala', 'Polonnaruwa', ['history'], 120, 'morning', 'low'],
    ['Minneriya National Park', 'Minneriya National Park', 'Polonnaruwa', ['wildlife'], 180, 'afternoon', 'high'],
    ['Kaudulla National Park', 'Kaudulla National Park', 'Polonnaruwa', ['wildlife'], 180, 'afternoon', 'medium'],
    ['Wasgamuwa National Park', 'Wasgamuwa National Park', 'Polonnaruwa', ['wildlife'], 180, 'morning', 'low'],

    // Matale
    ['Sigiriya Rock Fortress', 'Sigiriya', 'Matale', ['history', 'hiking'], 180, 'sunrise', 'high'],
    ['Pidurangala Rock', 'Pidurangala Rock', 'Matale', ['hiking'], 120, 'sunrise', 'medium'],
    ['Dambulla Cave Temple', 'Dambulla cave temple', 'Matale', ['history'], 90, 'morning', 'high'],
    ['Aluvihare Rock Temple', 'Aluvihare Rock Temple', 'Matale', ['history'], 60, 'any', 'low'],
    ['Nalanda Gedige', 'Nalanda Gedige', 'Matale', ['gems', 'history'], 45, 'any', 'low'],
    ['Riverston', 'Riverston', 'Matale', ['gems', 'hiking'], 180, 'morning', 'low'],
    ['Knuckles Mountain Range', 'Knuckles Mountain Range', 'Matale', ['hiking', 'gems'], 360, 'morning', 'low'],

    // Kandy
    ['Temple of the Sacred Tooth Relic', 'Temple of the Tooth', 'Kandy', ['history'], 90, 'any', 'high'],
    ['Royal Botanical Gardens, Peradeniya', 'Royal Botanical Gardens, Peradeniya', 'Kandy', ['hills'], 150, 'morning', 'high'],
    ['Kandy Lake', 'Kandy Lake', 'Kandy', ['hills'], 45, 'sunset', 'medium'],
    ['Embekka Devalaya', 'Embekka Devalaya', 'Kandy', ['history'], 45, 'any', 'low'],
    ['Gadaladeniya Vihara', 'Gadaladeniya Vihara', 'Kandy', ['history'], 45, 'any', 'low'],
    ['Lankatilaka Vihara', 'Lankatilaka Vihara', 'Kandy', ['history'], 45, 'any', 'low'],
    ['Degaldoruwa Raja Maha Vihara', 'Degaldoruwa Raja Maha Vihara', 'Kandy', ['history'], 45, 'any', 'low'],
    ['Udawatta Kele Sanctuary', 'Udawatta Kele Sanctuary', 'Kandy', ['hills'], 90, 'morning', 'low'],
    ['Ceylon Tea Museum', 'Ceylon Tea Museum', 'Kandy', ['hills'], 60, 'any', 'low'],
    ['Hanthana Mountain Range', 'Hanthana Mountain Range', 'Kandy', ['hiking'], 240, 'morning', 'low'],

    // Jaffna
    ['Nallur Kandaswamy Temple', 'Nallur Kandaswamy temple', 'Jaffna', ['history'], 60, 'morning', 'medium'],
    ['Jaffna Fort', 'Jaffna Fort', 'Jaffna', ['history'], 60, 'afternoon', 'low'],
    ['Jaffna Public Library', 'Jaffna Public Library', 'Jaffna', ['history'], 30, 'any', 'low'],
    ['Nagadeepa Purana Vihara', 'Nagadeepa Purana Vihara', 'Jaffna', ['history'], 180, 'morning', 'low'],
    ['Nainativu Nagapooshani Amman Temple', 'Nainativu Nagapooshani Amman Temple', 'Jaffna', ['history'], 60, 'morning', 'medium'],
    ['Delft Island', 'Delft Island', 'Jaffna', ['history'], 360, 'morning', 'low'],

    // Kurunegala
    ['Yapahuwa Rock Fortress', 'Yapahuwa', 'Kurunegala', ['history', 'gems'], 90, 'morning', 'low'],
    ['Ridi Viharaya', 'Ridi Viharaya', 'Kurunegala', ['gems', 'history'], 60, 'any', 'low'],
    ['Panduwasnuwara', 'Panduwasnuwara', 'Kurunegala', ['gems', 'history'], 60, 'any', 'low'],
    ['Arankele Forest Monastery', 'Arankele', 'Kurunegala', ['gems'], 90, 'morning', 'low'],
    ['Dambadeniya', 'Dambadeniya', 'Kurunegala', ['history'], 60, 'any', 'low'],

    // Nuwara Eliya
    ['Gregory Lake', 'Lake Gregory (Nuwara Eliya)', 'Nuwara Eliya', ['hills'], 60, 'afternoon', 'medium'],
    ['Horton Plains National Park', 'Horton Plains National Park', 'Nuwara Eliya', ['hiking', 'hills'], 240, 'sunrise', 'high'],
    ['Hakgala Botanical Garden', 'Hakgala Botanical Garden', 'Nuwara Eliya', ['hills'], 90, 'morning', 'medium'],
    ["Adam's Peak", "Adam's Peak", 'Nuwara Eliya', ['hiking'], 480, 'sunrise', 'high'],
    ['Seetha Amman Temple', 'Seetha Amman Temple', 'Nuwara Eliya', ['hills'], 30, 'any', 'medium'],
    ['Devon Falls', 'Devon Falls', 'Nuwara Eliya', ['hills'], 30, 'any', 'medium'],
    ["St. Clair's Falls", "St. Clair's Falls", 'Nuwara Eliya', ['hills'], 30, 'any', 'medium'],
    ['Ramboda Falls', 'Ramboda Falls', 'Nuwara Eliya', ['hills'], 45, 'any', 'medium'],

    // Badulla
    ['Ella', 'Ella, Sri Lanka', 'Badulla', ['hills'], 180, 'any', 'high'],
    ['Nine Arch Bridge', 'Nine Arch Bridge', 'Badulla', ['hills'], 60, 'morning', 'high'],
    ['Ella Rock', 'Ella Rock', 'Badulla', ['hiking'], 240, 'sunrise', 'medium'],
    ["Little Adam's Peak", "Little Adam's Peak", 'Badulla', ['hiking'], 120, 'sunrise', 'high'],
    ['Ravana Falls', 'Ravana Falls', 'Badulla', ['hills'], 30, 'any', 'medium'],
    ["Lipton's Seat", "Lipton's Seat", 'Badulla', ['hills'], 120, 'sunrise', 'medium'],
    ['Haputale', 'Haputale', 'Badulla', ['hills'], 120, 'morning', 'medium'],
    ['Diyaluma Falls', 'Diyaluma Falls', 'Badulla', ['hiking'], 180, 'morning', 'medium'],
    ['Dunhinda Falls', 'Dunhinda Falls', 'Badulla', ['gems', 'hills'], 90, 'morning', 'low'],
    ['Namunukula', 'Namunukula', 'Badulla', ['gems', 'hiking'], 300, 'sunrise', 'low'],
    ['Bambarakanda Falls', 'Bambarakanda Falls', 'Badulla', ['gems'], 60, 'any', 'low'],

    // Kegalle
    ['Pinnawala Elephant Orphanage', 'Pinnawala Elephant Orphanage', 'Kegalle', ['wildlife'], 120, 'morning', 'high'],
    ['Kitulgala White-Water Rafting', 'Kitulgala', 'Kegalle', ['hiking'], 240, 'morning', 'medium'],

    // Monaragala
    ['Maligawila Buddha Statue', 'Maligawila Buddha statue', 'Monaragala', ['gems'], 60, 'any', 'low'],
    ['Buduruwagala', 'Buduruwagala', 'Monaragala', ['gems'], 45, 'morning', 'low'],

    // Ratnapura
    ['Sinharaja Forest Reserve', 'Sinharaja Forest Reserve', 'Ratnapura', ['wildlife', 'hiking'], 360, 'morning', 'low'],
    ['Udawalawe National Park', 'Udawalawe National Park', 'Ratnapura', ['wildlife'], 180, 'morning', 'high'],
    ['Belihuloya', 'Belihuloya', 'Ratnapura', ['hiking'], 180, 'morning', 'low'],

    // Mannar
    ["Adam's Bridge", "Adam's Bridge", 'Mannar', ['gems'], 120, 'morning', 'low'],
    ['Thiruketheeswaram Temple', 'Thiruketheeswaram Temple', 'Mannar', ['gems'], 60, 'morning', 'low'],
    ['Mannar Fort', 'Mannar Fort', 'Mannar', ['gems'], 45, 'afternoon', 'low'],
    ['Shrine of Our Lady of Madhu', 'Shrine of Our Lady of Madhu', 'Mannar', ['gems'], 60, 'morning', 'low'],

    // Colombo
    ['Gangaramaya Temple', 'Gangaramaya Temple', 'Colombo', ['history'], 60, 'any', 'medium'],
    ['National Museum of Colombo', 'National Museum of Colombo', 'Colombo', ['history'], 90, 'any', 'medium'],
    ['Independence Memorial Hall', 'Independence Memorial Hall', 'Colombo', ['history'], 30, 'afternoon', 'low'],
    ['Jami Ul-Alfar Mosque', 'Jami Ul-Alfar Mosque', 'Colombo', ['history'], 30, 'any', 'medium'],
];
