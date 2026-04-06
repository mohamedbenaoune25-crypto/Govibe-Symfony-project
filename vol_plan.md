# Vol (Flight) Management System - Development Plan

## Project Overview
Flight management system where:
- **Admin** can create, read, update, delete flights and manage booking confirmations
- **Users** can search, view, and book available flights
- **Admin** can confirm/reject flight bookings
- System includes search and filtering functionality

---

## Architecture Overview

### Technology Stack
- **Framework:** Symfony 6.4
- **Database:** PostgreSQL (via Doctrine ORM)
- **Frontend:** Twig templates + Bootstrap/CSS
- **JavaScript:** Stimulus JS for interactivity
- **Authentication:** Symfony Security Bundle

---

## Database Schema

### 1. Flight Entity (`Vol`)
```
- id (Primary Key)
- departure_city (String) - Departure location
- arrival_city (String) - Arrival location
- airline_name (String) - Airline company
- departure_time (DateTime) - Flight departure
- arrival_time (DateTime) - Flight arrival
- flight_class (String) - Economy, Business, First Class
- total_seats (Integer) - Total available seats
- available_seats (Integer) - Remaining seats
- base_price (Decimal) - Price per ticket
- status (String) - 'active', 'cancelled'
- created_at (DateTime) - Creation timestamp
- updated_at (DateTime) - Last update timestamp
- admin (ManyToOne) - Admin user who created flight
- reservations (OneToMany) - Collection of reservations
```

### 2. Checkout Entity (`Checkout`)
```
- id (Primary Key)
- flight (ManyToOne) - Related flight
- user (ManyToOne) - Passenger/user
- passenger_name (String) - Full name
- passenger_email (String) - Email
- passenger_phone (String) - Phone number
- passenger_nbr (Integer) - Number of passengers
- total_prix (Integer/Decimal) - Total cost
- status_reservation (String) - 'pending', 'confirmed', 'rejected', 'cancelled'
- payment_method (String) - 'credit_card', 'debit_card', 'paypal'
- reservation_date (DateTime) - When booking was made
- seat_preference (String, optional)
- travel_class (String, optional)
```

### 3. User Entity (Existing - extends with roles)
```
- roles (Array) - ['ROLE_USER', 'ROLE_ADMIN']
- reservations (OneToMany) - User's flight bookings
```

---

## Feature Breakdown

### Phase 1: Flight Management (CRUD)

#### 1.1 Create Flight
- **Route:** `POST /admin/vols/new`
- **Controller:** `AdminVolController::new()`
- **Form:** `VolType` form with validation
- **Fields:**
  - Departure city (text, required)
  - Arrival city (text, required)
  - Airline name (text, required)
  - Departure time (datetime, required)
  - Arrival time (datetime, required)
  - Flight class (select: Economy/Business/First, required)
  - Total seats (integer, required, > 0)
  - Base price (decimal, required, > 0)

#### 1.2 Display Flights (User View)
- **Route:** `GET /`
- **Controller:** `HomeController::index()`
- **Features:**
  - Search by departure/arrival cities
  - Filter by class, date range, price range
  - Display available flights with:
    - Departure & arrival times
    - Airline name
    - Available seats count
    - Price per ticket
    - "Book Now" button
  - Modal dialog for flight details

#### 1.3 Read Flights (Admin View)
- **Route:** `GET /admin/vols`
- **Controller:** `AdminVolController::index()`
- **Features:**
  - Paginated list of all flights
  - Status indicator
  - Edit/Delete/View buttons
  - Search and filter

#### 1.4 Update Flight (Admin Only)
- **Route:** `POST /admin/vols/{id}/edit`
- **Controller:** `AdminVolController::edit()`
- **Features:**
  - Same form as create
  - Pre-filled with existing data
  - Update seat availability
  - Cannot edit if reservations exist

#### 1.5 Delete Flight (Admin Only)
- **Route:** `POST /admin/vols/{id}/delete`
- **Controller:** `AdminVolController::delete()`
- **Validation:**
  - Only delete cancelled flights without reservations
  - Soft delete approach recommended

---

### Phase 2: Flight Booking System

#### 2.1 Create Checkout / Book Flight (User Initiation)
- **Route:** `POST /vols/{id}/book`
- **Controller:** `VolController::bookFlight()`
- **Modal Form Fields:**
  - Passenger full name (text, required)
  - Email (email, required)
  - Phone (text, required)
  - Number of passengers (integer, 1-total_seats, required)
  - Flight class (select from available, required)
  - Payment method (select: Credit Card/Debit Card/PayPal, required)
- **Logic:**
  - Check seat availability
  - Create checkout with status 'pending'
  - Calculate total price (num_passengers × base_price)
  - Return to user dashboard
  - Ensure modal checkout forms use an explicit `action` URL to avoid posting to `/vols/`
  - Enforce form `action` and `method` directly in `CheckoutController` (`createForm` options) for new/edit routes

#### 2.2 User Checkout Dashboard
- **Route:** `GET /user/reservations`
- **Controller:** `UserController::reservations()`
- **Display:**
  - User's all checkouts grouped by status
  - Flight details: departure→arrival, time, airline
  - Passenger count
  - Total paid
  - Status badge (pending/confirmed/rejected)
  - Date booked
  - Booking controls (view details, edit pending, cancel)

#### 2.3 View Checkout Details
- **Route:** `GET /reservations/{id}`
- **Controller:** `VolController::viewBooking()`
- **Modal Display:**
  - Flight information
  - Passenger details
  - Payment method
  - Total amount
  - Reservation status
  - Reference number / QR code

---

### Phase 3: Admin Confirmation System

#### 3.1 Admin Checkouts Dashboard
- **Route:** `GET /admin/reservations`
- **Controller:** `AdminController::reservations()`
- **Features:**
  - All pending checkouts
  - Filter by status (pending/confirmed/rejected)
  - Search by passenger name, email, flight
  - Date range filter
  - Paginated list
- **Display per Reservation:**
  - Date booked
  - Flight route (Departure → Arrival)
  - Status indicator
  - Passenger count
  - Airline
  - Total paid
  - Action buttons

#### 3.2 Confirm Checkout (Admin)
- **Route:** `POST /admin/reservations/{id}/confirm`
- **Controller:** `AdminController::confirmReservation()`
- **Logic:**
  - Update checkout status to 'confirmed'
  - Update flight available_seats (-num_passengers)
  - Send confirmation email to user
  - Set confirmed_date timestamp

#### 3.3 Reject Checkout (Admin)
- **Route:** `POST /admin/reservations/{id}/reject`
- **Controller:** `AdminController::rejectReservation()`
- **Logic:**
  - Update checkout status to 'rejected'
  - Do not deduct from available_seats
  - Send rejection email to user

---

## UI/UX Design (Per Screenshots)

### Design Consistency
- **Color Scheme:** Teal/green primary, white cards, dark text
- **Layout:** Hero image background + white cards overlay
- **Components:** 
  - Navbar with navigation links
  - Search bar with filters
  - Flight cards with key info
  - Modal dialogs for detailed form
  - Status badges for reservations
- **Background Asset:** `public/images/home-hero5.png` is now used as the public app background image
- Updated `vol/index` and `checkout/index` hero/background overrides to use `home-hero5.png` so the change is visible on those pages too

### Key Pages
1. **Home/Flights Page**
   - Hero section with background image
   - Search bar (destination, date, passengers, class)
   - Grid of available flights
  - Modal for booking
  - Checkout CTA opens the booking form in the centered blurred modal with spinner loading

2. **User Dashboard**
   - My Reservations section
   - Reservation cards with status
   - Quick actions (view, edit, cancel)

3. **Admin Dashboard**
   - Reservations Tracking
   - Pending bookings queue
   - Quick confirm/reject actions
   - Flight management panel

---

## Implementation Order

### Step 1: Database & Entity Setup
- [ ] Create `Vol` entity with all fields
- [ ] Create `Reservation` entity
- [ ] Create database migration
- [ ] Update User entity relationships

### Step 2: Admin Flight Management
- [ ] Create `VolType` form
- [ ] Create `AdminVolController` with CRUD
- [ ] Create flight list view (admin)
- [ ] Create flight form views (create/edit)
- [ ] Add delete functionality with validation

### Step 3: User Flight Search & Booking
- [ ] Create search/filter repository methods
- [ ] Create home page with flight list
- [ ] Create booking modal form
- [ ] Create `VolController` for user actions
- [ ] Add seat availability validation

### Step 4: Checkout Management
- [ ] Create user checkouts dashboard
- [ ] Create checkout detail view
- [ ] Add booking history/status tracking

### Step 5: Admin Confirmation System
- [ ] Create admin checkouts dashboard
- [ ] Create checkout cards with status
- [ ] Implement confirm/reject actions
- [ ] Add email notifications

### Step 6: Styling & Polish
- [ ] Apply consistent design across all pages
- [ ] Ensure responsive design
- [ ] Add error handling & validation messages
- [ ] Test all workflows
- [ ] Open details in centered modal overlays with blurred background
- [ ] Animate vol, checkout, and forum cards with lazy reveal

---

## File Structure

```
src/
  Controller/
    VolController.php (User flight actions)
    AdminVolController.php (Admin flight CRUD)
    AdminController.php (Admin checkouts)
  Entity/
    Vol.php (Flight entity - will be created)
    Checkout.php (Booking entity - already exists)
  Form/
    VolType.php (Flight form - will be created)
  Repository/
    VolRepository.php
    CheckoutRepository.php
templates/
  vol/
    index.html.twig (User flights page)
    book_modal.html.twig
    booking_details.html.twig
  admin/
    vol/
      index.html.twig (Admin flights list)
      form.html.twig (Create/Edit flight)
    checkouts.html.twig (Admin checkouts)
  user/
    checkouts.html.twig (User bookings)
```

---

## Validation & Business Rules

### Flight Validation
- Departure time must be before arrival time
- Both cities required and non-empty
- Total seats must be > 0
- Base price must be > 0
- Only admins can create/edit flights

### Checkout Validation
- User must be logged in
- Flight must be active and have available seats
- Number of passengers ≤ available seats
- Email must be valid
- Phone must be valid format
- Only user can edit pending bookings
- Admin only can confirm/reject

### Status Transitions
- Flight: active → cancelled (admin only)
- Reservation: pending → confirmed/rejected (admin only)
- User can cancel pending checkout

---

## Security & Permissions

### Roles
- **ROLE_USER:** Can view flights, book, manage own checkouts
- **ROLE_ADMIN:** Full access to flights and reservations management

### Access Control
- Admin controllers require ROLE_ADMIN
- User controllers require ROLE_USER
- Users can only view/edit their own checkouts
- Admin can view all checkouts

---

## Testing Checklist

- [ ] Admin can create flight with all fields
- [ ] Admin can edit flight details
- [ ] Admin can delete cancelled flights
- [ ] Users see available flights matching search
- [ ] Users can book with valid data
- [ ] Seat count decrements on confirmation
- [ ] Admin can confirm pending booking
- [ ] Admin can reject booking
- [ ] User receives appropriate emails
- [ ] Form validation works
- [ ] Unauthorized access is blocked
- [ ] Search and filters work correctly

---

## Notes
- Use Doctrine migrations for database changes
- Implement proper error handling and logging
- Add flash messages for user feedback
- Ensure responsive design for mobile
- Follow Symfony best practices
- Use dependency injection for services
- Add proper authorization checks
