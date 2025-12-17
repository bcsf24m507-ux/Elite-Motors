# Status Meanings in Car Rental System

## 🚗 CAR STATUSES (In `cars` table)

These statuses are stored in the `cars` table and indicate the **physical/operational state** of the car.

### 1. **AVAILABLE** ✅
- **Meaning**: Car is ready and available for booking
- **When**: Car is in good condition, not rented, not in maintenance
- **Can be booked?**: YES (if no active bookings)
- **Display**: Green badge "Available"
- **Example**: A car sitting in the parking lot, ready to rent

---

### 2. **RENTED** 🚙
- **Meaning**: Car is currently being used by a customer
- **When**: Car is physically with a customer right now
- **Can be booked?**: NO
- **Display**: Yellow badge "Rented"
- **Example**: Customer picked up the car and is driving it

---

### 3. **MAINTENANCE** 🔧
- **Meaning**: Car is being serviced or repaired
- **When**: Car is in the garage for repairs, oil change, inspection, etc.
- **Can be booked?**: NO
- **Display**: Blue badge "Maintenance"
- **Example**: Car needs brake repair, so it's in the shop

---

### 4. **UNAVAILABLE** ❌
- **Meaning**: Car is not available for any reason
- **When**: Car is damaged, sold, retired, or temporarily out of service
- **Can be booked?**: NO
- **Display**: Red badge "Unavailable"
- **Example**: Car was in an accident and needs major repairs

---

## 📅 BOOKING STATUSES (In `bookings` table)

These statuses are stored in the `bookings` table and indicate the **state of a booking request**.

### 1. **PENDING** ⏳
- **Meaning**: Customer has requested a booking, waiting for admin confirmation
- **When**: Customer just created a booking, admin hasn't approved yet
- **Car available?**: NO (car is reserved, waiting for approval)
- **Display**: Yellow badge "Pending"
- **Flow**: Customer books → Status = PENDING → Admin confirms → Status = CONFIRMED
- **Example**: Customer booked a car for next week, admin needs to verify availability

---

### 2. **CONFIRMED** ✅
- **Meaning**: Payment has been paid, booking is confirmed
- **When**: Payment status is 'paid', booking is automatically confirmed
- **Car available?**: ❌ NO (car is reserved for this booking)
- **Display**: Blue badge "Confirmed"
- **Flow**: PENDING → Payment Paid → CONFIRMED (automatic) → Customer picks up → ACTIVE
- **Example**: Customer paid for the booking, car is now reserved and confirmed

---

### 3. **ACTIVE** 🚗
- **Meaning**: Customer has picked up the car and is currently using it
- **When**: Car is physically with the customer, rental period is ongoing
- **Car available?**: NO (car is being used right now)
- **Display**: Green badge "Active"
- **Flow**: CONFIRMED → Customer picks up car → ACTIVE → Customer returns → COMPLETED
- **Example**: Customer picked up car on Dec 1, will return on Dec 5 (currently Dec 3)

---

### 4. **COMPLETED** ✅
- **Meaning**: Customer has returned the car, rental period is finished
- **When**: Car is back, rental period ended successfully
- **Car available?**: YES (car is free again, can be booked)
- **Display**: Gray badge "Completed"
- **Flow**: ACTIVE → Customer returns car → COMPLETED
- **Example**: Customer returned car on Dec 5, rental finished successfully

---

### 5. **CANCELLED** ❌
- **Meaning**: Booking was cancelled (by customer or admin)
- **When**: Customer cancelled before pickup, or admin cancelled the booking
- **Car available?**: YES (car is free again, can be booked)
- **Display**: Red badge "Cancelled"
- **Flow**: PENDING/CONFIRMED → Cancelled → CANCELLED
- **Example**: Customer changed mind and cancelled, or admin cancelled due to car issue

---

## 🔄 STATUS FLOW DIAGRAM

### Booking Lifecycle:
```
PENDING (payment not paid) → CONFIRMED (payment paid) → ACTIVE (trip started) → COMPLETED (trip ended)
   ↓                              ↓
CANCELLED                    CANCELLED
```

### Car Availability Rules:
```
PENDING → ✅ Car AVAILABLE (not reserved)
CONFIRMED → ❌ Car NOT AVAILABLE (reserved)
ACTIVE → ❌ Car NOT AVAILABLE (in use)
COMPLETED → ✅ Car AVAILABLE (free again)
CANCELLED → ✅ Car AVAILABLE (free again)
```

### Car Availability:
```
AVAILABLE → (Booked) → RENTED → AVAILABLE
    ↓
MAINTENANCE → AVAILABLE
    ↓
UNAVAILABLE
```

---

## 📊 HOW STATUSES AFFECT AVAILABILITY

### Car Status + Booking Status = Actual Availability

| Car Status | Booking Status | Payment Status | Is Car Available? | Why? |
|------------|----------------|----------------|-------------------|------|
| `available` | None | - | ✅ YES | Car is ready, no bookings |
| `available` | `pending` | `pending` | ✅ YES | Payment not paid, car NOT reserved |
| `available` | `pending` | `paid` | ❌ NO | Payment paid, booking auto-confirmed |
| `available` | `confirmed` | `paid` | ❌ NO | Booking confirmed, car reserved |
| `available` | `active` | `paid` | ❌ NO | Car is being used right now |
| `available` | `completed` | `paid` | ✅ YES | Booking finished, car is free |
| `available` | `cancelled` | Any | ✅ YES | Booking cancelled, car is free |
| `rented` | Any | Any | ❌ NO | Car is physically with customer |
| `maintenance` | Any | Any | ❌ NO | Car is in the shop |
| `unavailable` | Any | Any | ❌ NO | Car is not available |

### ⚠️ IMPORTANT RULES:

**Car Availability Based on Booking Status:**
- ✅ **PENDING** → Car is AVAILABLE (payment not done, car not reserved)
- ❌ **CONFIRMED** → Car is NOT AVAILABLE (payment paid, car reserved)
- ❌ **ACTIVE** → Car is NOT AVAILABLE (car is being used)
- ✅ **COMPLETED** → Car is AVAILABLE (trip ended, car is free)
- ✅ **CANCELLED** → Car is AVAILABLE (booking cancelled, car is free)

**Status Transitions:**
- **PENDING → CONFIRMED**: Automatically happens when payment is marked as 'paid' in Manage Payments
- **CONFIRMED → ACTIVE**: Admin manually updates when customer picks up the car (trip starts)
- **ACTIVE → COMPLETED**: Admin manually updates when customer returns the car (trip ends)

---

## 💡 REAL-WORLD EXAMPLES

### Example 1: Normal Booking Flow
1. **Car Status**: `available`
2. Customer books car → **Booking Status**: `pending`
3. Admin approves → **Booking Status**: `confirmed`
4. Customer picks up → **Booking Status**: `active`, **Car Status**: `rented`
5. Customer returns → **Booking Status**: `completed`, **Car Status**: `available`

### Example 2: Cancelled Booking
1. **Car Status**: `available`
2. Customer books car → **Booking Status**: `pending`
3. Customer cancels → **Booking Status**: `cancelled`
4. **Car Status**: Still `available` (car is free again)

### Example 3: Car in Maintenance
1. **Car Status**: `available`
2. Car needs service → **Car Status**: `maintenance`
3. Car cannot be booked (even if no bookings)
4. After service → **Car Status**: `available`

### Example 4: Completed Booking
1. **Car Status**: `available`
2. Customer books → **Booking Status**: `confirmed`
3. Customer uses car → **Booking Status**: `active`
4. Customer returns → **Booking Status**: `completed`
5. **Car Status**: Still `available` (ready for next booking)

---

## 🎯 KEY POINTS TO REMEMBER

1. **Car Status** = Physical/operational state of the car
2. **Booking Status** = State of a rental request
3. **Available Cars** = Cars with status='available' AND no active bookings (pending/confirmed/active)
4. **Completed/Cancelled bookings** = Car becomes available again
5. **Pending/Confirmed/Active bookings** = Car is NOT available

---

## 🔍 WHERE TO SEE STATUSES

- **Car Status**: Manage Cars page, Dashboard
- **Booking Status**: Manage Bookings (admin), My Bookings (customer)
- **Available Cars**: Dashboard → Click "Available Cars" card

---

## ⚠️ IMPORTANT NOTES

- A car can have status='available' but still show as "Booked" if it has an active booking
- Only `pending`, `confirmed`, and `active` bookings block car availability
- `completed` and `cancelled` bookings do NOT block availability
- Car status `rented`, `maintenance`, or `unavailable` always blocks availability

