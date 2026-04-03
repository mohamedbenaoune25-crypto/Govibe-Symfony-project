# Vol (Flight) System - Project Analysis

## Current State Assessment

### ✅ EXISTING COMPONENTS

#### 1. Vol Entity (Flight)
**Status:** Partially Complete
- ✓ flightId (String, Primary Key)
- ✓ departureAirport (String)
- ✓ destination (String)
- ✓ departureTime (Time)
- ✓ arrivalTime (Time)
- ✓ classeChaise (String - flight class)
- ✓ airline (String)
- ✓ prix (Integer - price)
- ✓ availableSeats (Integer)
- ✓ description (Text, optional)

**Missing Fields:**
- ❌ total_seats (total capacity)
- ❌ status (active/cancelled)
- ❌ createdAt/updatedAt (timestamps)
- ❌ admin relationship (ManyToOne to Personne)
- ❌ checkouts relationship (OneToMany to Checkout)
- ❌ date field (departure date separate from time)

**Issues:**
- departureTime/arrivalTime are TIME type (should be DATETIME)
- No timestamps for audit trail
- No status tracking

#### 2. Checkout Entity
**Status:** Flight booking model already exists
- Mapped to `Vol` and `Personne`
- Contains passenger details, payment method, seat preference, travel class
- **Action:** build the CRUD and workflow around this entity instead of creating a new reservation table

#### 3. AdminVolController
**Status:** 90% Complete
- ✓ list all flights
- ✓ create new flight
- ✓ edit flight
- ✓ delete flight
- ❌ Missing: Flight status management (active/cancelled)

#### 4. VolController
**Status:** 50% Complete
- ✓ list flights with search by origin/destination
- ❌ Missing: booking functionality
- ❌ Missing: view flight details
- ❌ Missing: user checkouts dashboard

#### 5. VolType Form
**Status:** 95% Complete
- ✓ All flight fields
- ✓ Validation ready
- ❌ Could add total_seats field

### ❌ MISSING COMPONENTS

#### 1. Checkout CRUD for flights (NEW/TO COMPLETE)
The project already has a `Checkout` entity, but it still needs the flight-booking workflow around it.
Need to confirm or add:
- id
- flight relation (`Vol`)
- user relation (`Personne`)
- passenger_name (String)
- passenger_email (String)
- passenger_phone (String)
- passenger_nbr (Integer)
- total_prix (Integer/Decimal)
- status_reservation (pending/confirmed/rejected)
- payment_method (credit_card/debit_card/paypal)
- reservation_date (DateTime)
- seat_preference (optional)
- travel_class (optional)

#### 2. Checkout Form/Controller Layer (NEW)
- Passenger name, email, phone
- Number of passengers
- Payment method selection
- CRUD pages for the user side

#### 3. Templates (NEW)
- `vol/index.html.twig` - User flights list with search
- `vol/book_modal.html.twig` - Booking form modal
- `vol/details.html.twig` - Flight detail view
- `checkout/index.html.twig` - User checkout history / CRUD
- `admin/checkouts.html.twig` - Admin booking management
- `admin/vols/form.html.twig` - Create/Edit flight form

#### 4. Admin Checkout Controller Methods (NEW)
- List all bookings with filters
- Confirm booking
- Reject booking
- Send email notifications

#### 5. User Booking Controller Methods (NEW)
- Create checkout (POST)
- View user checkouts (GET)
- View checkout details (GET)
- Update/cancel own checkout when allowed

---

## Implementation Strategy

### Phase 1: Database Updates (1-2)
1. **Update Vol Entity**
   - Add missing fields (total_seats, status, createdAt, updatedAt)
   - Add relationships (admin ManyToOne, reservations OneToMany)
   - Fix date/time fields (use DATETIME instead of TIME)
   - Create migration

2. **Use Checkout entity for flight bookings**
   - Confirm current fields are sufficient
   - Add any missing attributes only if the UI/workflow needs them
   - Keep repository and migration aligned with existing schema

### Phase 2: Forms & Controllers (3-5)
3. **Create CheckoutType Form**
   - Passenger details
   - Payment method
   - Validation

4. **Update Controllers**
   - Add flight booking to VolController
   - Add user checkout view
   - Update AdminVolController with status updates
   - Create AdminReservationController for admin dashboard

5. **Create Repository Methods**
   - Search flights with filters
   - Get pending checkouts
   - Get user checkouts

### Phase 3: Templates & UI (6-7)
6. **Create User Templates**
   - Flights list page (matching design)
   - Booking modal
   - Checkout dashboard
   - Checkout details

7. **Create Admin Templates**
   - Flights admin list
   - Flights form
   - Reservations admin dashboard

### Phase 4: Testing & Polish (8)
8. **Testing & Refinement**
   - Test all workflows
   - Email notifications
   - Responsive design
   - Bug fixes

---

## Updated Development Order

```
1. ✏️  Update Vol entity (add missing fields)
   ↓
2. 🆕 Use Checkout entity for flight bookings
   ↓
3. 🗂️  Create database migrations and run them
   ↓
4. 📋 Create CheckoutType form
   ↓
5. 🎮 Update VolController (add booking)
   ↓
6. 🎮 Create/Update AdminCheckoutController
   ↓
7. 🎮 Update AdminVolController (status management)
   ↓
8. 🎨 Create all templates (matching design)
   ↓
9.  📧 Add email notifications
   ↓
10. 🧪 Test workflows & bug fixes
```

---

## Design Matching Notes

From screenshots analysis:
- **Color scheme:** Teal/turquoise headers, white cards, green action buttons
- **Layout:** Hero background + overlaid white content cards
- **Flight cards:** Show route, time, airline, seats, price, status
- **Reservation cards:** Show date, route, passenger count, total, status badge
- **Modals:** White background, rounded corners, status indicators
- **Admin dashboard:** Side navigation menu, grid of reservation cards

---

## Files to Create
```
src/Entity/Checkout.php
src/Form/CheckoutType.php
src/Repository/CheckoutRepository.php
src/Controller/CheckoutController.php
templates/vol/index.html.twig
templates/vol/book_modal.html.twig
templates/vol/details.html.twig
templates/checkout/index.html.twig
templates/admin/checkouts.html.twig
templates/admin/vols/form.html.twig
```

## Files to Modify
```
src/Entity/Vol.php
src/Form/VolType.php
src/Controller/VolController.php
src/Controller/AdminVolController.php
src/Repository/VolRepository.php
templates/admin/vols/index.html.twig
```

## Database Migrations Needed
```
1. Modify "vol" table (add fields & indexes)
2. Create "flight_reservation" table
3. Add foreign key constraints
```
