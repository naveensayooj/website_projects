import json
import sys


def main() -> None:
    try:
        raw = json.load(sys.stdin)
    except Exception:
        print(json.dumps({"error": "invalid input"}))
        return

    users = int(raw.get("users", 0))
    trainers = int(raw.get("trainers", 0))
    bookings = int(raw.get("bookings", 0))
    categories = raw.get("categories", [])

    result = {
        "totals": {
            "users": users,
            "trainers": trainers,
            "bookings": bookings,
        },
        "bookings_per_trainer": float(bookings) / trainers if trainers else 0.0,
        "bookings_per_user": float(bookings) / users if users else 0.0,
        "top_categories": categories,
    }

    print(json.dumps(result))


if __name__ == "__main__":
    main()

