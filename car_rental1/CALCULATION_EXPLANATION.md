# Car Availability Calculation Explanation

## 📊 How Car Availability is Calculated

### **1. Total Cars Calculation**
```sql
SELECT COUNT(*) as total FROM cars
```
- **What it counts**: ALL cars in the database, regardless of status
- **Result**: Total number of cars in the system

---

### **2. Available Cars Calculation** ⭐ (Most Important)

```sql
SELECT c.id, c.brand, c.model 
FROM cars c 
WHERE c.status = 'available' 
AND c.id NOT IN (
    SELECT DISTINCT car_id FROM bookings 
    WHERE status IN ('pending', 'confirmed', 'active')
    AND end_date >= CURDATE()
)
```

#### **Step-by-Step Logic:**

**Step 1: Check Car Status**
- Only cars with `status = 'available'` are considered
- Cars with status: `'rented'`, `'maintenance'`, or `'unavailable'` are EXCLUDED

**Step 2: Check for Active Bookings**
- Look in the `bookings` table
- Find bookings where:
  - Status is: `'confirmed'` or `'active'` ONLY
  - AND `end_date >= CURDATE()` (booking hasn't ended yet)
- **IMPORTANT RULES**:
  - ✅ PENDING bookings → Car is AVAILABLE (payment not done)
  - ❌ CONFIRMED bookings → Car is NOT available
  - ❌ ACTIVE bookings → Car is NOT available
  - ✅ COMPLETED bookings → Car is AVAILABLE (trip ended)
  - ✅ CANCELLED bookings → Car is AVAILABLE (booking cancelled)

**Step 3: Exclude Booked Cars**
- If a car has ANY confirmed or active booking (from Step 2), it is EXCLUDED from available count
- Only cars with NO confirmed/active bookings are counted as available
- **PENDING bookings**: Car remains available (payment not done, car not reserved)
- **COMPLETED/CANCELLED bookings**: Car is available (these statuses don't block)

---

### **3. Example Scenario**

**Database State:**
- Total Cars: 10
- Car Statuses:
  - 8 cars with status = 'available'
  - 1 car with status = 'maintenance'
  - 1 car with status = 'unavailable'

**Bookings:**
- Booking #1: Car ID 1, Status: 'confirmed', Payment: 'paid', End Date: 2025-12-20 (future)
- Booking #2: Car ID 2, Status: 'pending', Payment: 'pending', End Date: 2025-12-25 (future)
- Booking #3: Car ID 3, Status: 'completed', Payment: 'paid', End Date: 2025-11-15 (past)
- Booking #4: Car ID 4, Status: 'cancelled', Payment: 'pending', End Date: 2025-12-10 (past)

**Calculation:**
1. Start with 8 cars (status = 'available')
2. Exclude Car ID 1 (has 'confirmed' booking - blocks availability)
3. Keep Car ID 2 (has 'pending' booking BUT payment not paid - car is AVAILABLE)
4. Keep Car ID 3 (booking is 'completed' - not active, car is free)
5. Keep Car ID 4 (booking is 'cancelled' - not active, car is free)

**Result: Available Cars = 7** (8 - 1 = 7)

**Note**: If Booking #2 payment becomes 'paid', it will auto-confirm and Car ID 2 will be excluded.

---

### **4. Status Meanings**

| Car Status | Meaning | Counted as Available? |
|------------|---------|------------------------|
| `available` | Car is ready for booking | ✅ YES (if no active bookings) |
| `rented` | Car is currently rented | ❌ NO |
| `maintenance` | Car is being serviced | ❌ NO |
| `unavailable` | Car is not available | ❌ NO |

| Booking Status | Payment Status | Meaning | Makes Car Unavailable? |
|----------------|----------------|---------|------------------------|
| `pending` | `pending` | Booking requested, payment not paid | ❌ NO (car remains available) |
| `pending` | `paid` | Payment paid, auto-confirmed | ✅ YES (car is reserved) |
| `confirmed` | `paid` | Booking confirmed | ✅ YES |
| `active` | `paid` | Car is currently being used | ✅ YES |
| `completed` | `paid` | Booking finished | ❌ NO (car is free) |
| `cancelled` | Any | Booking was cancelled | ❌ NO (car is free) |

---

### **5. Why the Calculation Might Show Different Numbers**

**Example: 10 Total Cars, 6 Bookings, but 7 Available?**

This happens when:
- Some bookings are `'completed'` or `'cancelled'` (these don't block availability)
- Some bookings have `end_date < CURDATE()` (past bookings don't block availability)
- Some cars might have status = `'maintenance'` or `'unavailable'` (not counted in available)

**Correct Formula:**
```
Available Cars = Cars with status='available' 
                 MINUS 
                 Cars with active bookings (pending/confirmed/active with future end_date)
```

---

### **6. In Manage Cars Page**

Each car shows its **actual status** which considers:
1. Database status field (`available`, `rented`, `maintenance`, `unavailable`)
2. Active bookings check (if car has active booking, shows "Booked" even if status is "available")

**Display Logic:**
- If car has active booking → Shows "Booked" (yellow badge)
- If car status = 'available' AND no active bookings → Shows "Available" (green badge)
- If car status = 'maintenance' → Shows "Maintenance" (blue badge)
- If car status = 'unavailable' → Shows "Unavailable" (red badge)

---

## 🔍 How to Verify the Calculation

1. **Check Dashboard**: Click on "Available Cars" card to see the list
2. **Check Manage Cars**: See which cars show "Booked" vs "Available"
3. **Check Bookings**: See which bookings are active (pending/confirmed/active with future dates)

---

## 📝 Summary

**Available Cars = Cars that are:**
- ✅ Status = 'available' in database
- ✅ No active bookings (pending/confirmed/active with end_date >= today)
- ✅ Not in maintenance
- ✅ Not marked as unavailable

**The system automatically excludes booked cars from the available count!**

