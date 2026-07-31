"""Booking scenarios the Dispatcher hands out to Performer workers."""

SCENARIOS = [
    {
        "name": "Family Nature Trip",
        "email": "family.nature@example.com",
        "country": "United Kingdom",
        "interests": ["hill-country", "wildlife", "family"],
        "budget": "medium",
        "days": 5,
        "travelers": 4,
    },
    {
        "name": "Honeymoon Beaches",
        "email": "honeymoon.beach@example.com",
        "country": "Australia",
        "interests": ["beaches", "wellness"],
        "budget": "high",
        "days": 6,
        "travelers": 2,
    },
    {
        "name": "Backpacker Culture Loop",
        "email": "backpacker.culture@example.com",
        "country": "Germany",
        "interests": ["culture", "adventure"],
        "budget": "low",
        "days": 4,
        "travelers": 1,
    },
]
