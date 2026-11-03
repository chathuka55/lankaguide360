<?php

/*
 * Sample photos for hotels without photos of their own (lg:hotel-sample-photos). All from
 * Wikimedia Commons under CC0 / CC BY / CC BY-SA; author and licence are read from Commons and
 * shown with each photo. Generic rooms, views and food only, never a recognisable named hotel
 * presented as another one, and the site always labels them "Sample photo".
 *
 * Columns: Commons title, name, subject (room|view|pool|food), tiers, hotel types, settings
 * (null = any). Settings come from the hotel's district: hill country, beach or inland.
 */
return [
    // Rooms
    ['File:Lishan Guest House bedroom.jpg', 'Guest house room', 'room', ['budget'], ['guest_house', 'hotel', 'resort'], null],
    ['File:Vintage double bed with white bed sheets in a modern hotel room.jpg', 'Simple double room', 'room', ['budget'], ['hotel', 'guest_house'], null],
    ['File:Hostel 6-bed dorm room, Kuching, Malaysia.jpg', 'Hostel dorm room', 'room', null, ['hostel'], null],
    ['File:Apartment hotel Nice.jpg', 'Apartment with kitchenette', 'room', null, ['apartment'], null],
    ['File:Hotel room Sri Lanka Photo212.jpg', 'Twin room', 'room', ['premium', 'budget'], ['hotel', 'resort'], null],
    ['File:Hotel room Sri Lanka Photo213.jpg', 'Double room', 'room', ['premium'], ['hotel', 'resort'], null],
    ['File:Bed in hotel room 2.jpg', 'Superior room', 'room', ['premium'], ['hotel', 'resort'], null],
    ['File:Bed in hotel room 5.jpg', 'Deluxe room', 'room', ['premium', 'luxury'], ['hotel', 'resort'], null],
    ['File:Thiranagama Sri Lanka Riff Hotel 5-star resort Suite bedroom.jpg', 'Suite with a sea view', 'room', ['luxury'], ['hotel', 'resort'], ['beach']],
    ['File:Canopy bed of Amantaka Suite in Amantaka luxury Resort & Hotel in Luang Prabang Laos.jpg', 'Suite with a canopy bed', 'room', ['luxury'], ['hotel', 'resort'], null],

    // Views (by setting)
    ['File:Marakolliya Beach Scene - Ganesh Garden Beach Cabanas - Tangalla - Sri Lanka - 02 (13893196909).jpg', 'Beach with cabanas', 'view', null, null, ['beach']],
    ['File:Thiranagama Sri Lanka Riff Hotel 5-star resort Rooftop sunset dining.jpg', 'Sunset over the sea', 'view', ['premium', 'luxury'], null, ['beach']],
    ['File:View from Balcony of Rawana Holiday Resort - Ella - Sri Lanka (14112388975).jpg', 'Misty hill-country view', 'view', null, null, ['hill']],
    ['File:View from 98 acres Resort 2023-04-24.jpg', 'Valley view in the hills', 'view', ['premium', 'luxury'], null, ['hill']],
    ['File:Sigiriya Village (4).jpg', 'Garden in the countryside', 'view', null, null, ['inland']],
    ['File:Resort Sinharaja Rainforest.jpg', 'Cabins by the rainforest', 'view', ['budget', 'premium'], null, ['inland', 'hill']],

    // Pools (premium, luxury and resorts)
    ['File:Negombo Beach resort pool (Unsplash).jpg', 'Pool at sunset', 'pool', ['premium', 'luxury'], null, ['beach']],
    ['File:Swimming Pool in a hotel in Tamil Nadu 03.jpg', 'Swimming pool', 'pool', ['premium', 'luxury'], null, null],

    // Sri Lankan food
    ['File:Sri Lankan breakfast by String hoppers.jpg', 'Sri Lankan breakfast', 'food', null, null, null],
    ['File:String hoppers for breakfast in Colombo.jpg', 'String hoppers and curries', 'food', null, null, null],
    ['File:Rice and Curry by ARLAWKA.jpg', 'Rice and curry', 'food', null, null, null],
    ['File:SL-rice and curry.jpg', 'Rice and curry lunch', 'food', null, null, null],
];
