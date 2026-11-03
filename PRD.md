# Product Requirement Document (PRD)

## 1. Document Overview

* **Project Name:** Polindra Suruh (Polindra Hub) - Precision Maps & Midtrans Payment Integration
* **Version:** 3.0
* **Date:** October 31, 2026
* **Target Audience:** Polindra Academic Community (Students, Staff, Drivers, Admin)
* **Status:** In Progress / Revised Specification

---

## 2. Product Vision & Goals

Transform **Polindra Suruh** into a high-precision, interactive micro-logistics campus platform with real-time visual route animation and fully functioning automated digital payments via Midtrans.

### Key Enhancements

1. **Precision Location Input & Interactive Map:** Integrate Google Places Autocomplete API and Google Maps JavaScript API interactive map with dynamic markers and polyline route animation for precise pick-up and drop-off coordinates.
2. **Fully Functioning Midtrans Integration:** Resolve `config.php` Server Key placeholders and integrate Midtrans Snap JS pop-up for instant QRIS, GoPay, ShopeePay, and Virtual Account transactions.
3. **Automated Order & Payment Status Lifecycle:** Synchronize client-side payment completion events and server-side webhooks to update the order status automatically.

---

## 3. Scope of Work & Functional Requirements

### 3.1 Enhanced Maps & Visual Route Module (`order.php`)

* **Google Places Autocomplete:** Attach Places Autocomplete listeners to both input fields (`origin_location` & `destination_location`) restricted to Indonesia bounds to ensure exact geo-coordinates ($lat, lng$).
* **Interactive Map Container:** Render an embedded Google Map container (`<div id="map"></div>`) with custom marker icons:
  * 🔴 **Green Pin / Pickup Icon:** Marks origin location.
  * 🔵 **Red Pin / Drop-off Icon:** Marks destination location.
* **Animated Polyline Route:** 
  * Fetch actual driving routes via `google.maps.DirectionsService`.
  * Render a smoothed polyline path between origin and destination with smooth zoom/pan transition (`map.panToBounds()`).
* **Accurate Distance & Fare Engine:**
  * Calculate exact driving route distance (in KM) from Directions/Distance Matrix Service.
  * **Formula:**
    $$\text{Total Fare} = \text{Base Fare (Rp 3,000)} + (\lceil\text{Distance (KM)}\rceil \times \text{Per KM Rate (Rp 2,000)})$$

### 3.2 Midtrans Payment Integration Module

* **Configuration Management (`config.php`):**
  * Store active Midtrans Merchant Server Key, Client Key, and Environment Flag (`isProduction = false` for Sandbox).
  * Configure Midtrans PHP SDK / REST API Client header authorization using `base64_encode(SERVER_KEY + ':')`.
* **Snap Token Generation:**
  * Upon form submit with `metode_bayar = 'qris'`, server stores initial pending record in database and calls Midtrans Snap API (`https://app.sandbox.midtrans.com/snap/v1/transactions`).
  * Pass `order_id`, `gross_amount` (calculated total fare), customer details, and item details.
  * Return valid `snap_token` to frontend JSON response.
* **Frontend Snap Pop-Up Execution:**
  * Trigger `snap.pay(snapToken)` without page refresh.
  * Handle `onSuccess`, `onPending`, `onError`, and `onClose` JS callbacks to redirect user to `pesanan_saya.php`.
* **Webhook Listener (`midtrans_notification.php`):**
  * Handle HTTP POST notifications from Midtrans.
  * Validate signature hash (`SHA512(order_id + status_code + gross_amount + ServerKey)`).
  * Update database field `payment_status` to `'paid'` / `'failed'` and order `status` to `'Menunggu Driver'`.

---

## 4. Database Schema Requirements (`pesanan` Table)

Ensure the MySQL table `pesanan` contains the following fields:

| Field Name | Type | Description |
| --- | --- | --- |
| `id` | `INT AUTO_INCREMENT` | Primary Key |
| `user_id` | `INT` | Customer ID |
| `driver_id` | `INT NULL` | Assigned Driver ID |
| `origin_location` | `TEXT` | Specific pickup address / Google Place Name |
| `origin_lat` | `DECIMAL(10,8) NULL` | Pickup Latitude |
| `origin_lng` | `DECIMAL(11,8) NULL` | Pickup Longitude |
| `destination_location` | `TEXT` | Specific delivery address / Google Place Name |
| `destination_lat` | `DECIMAL(10,8) NULL` | Destination Latitude |
| `destination_lng` | `DECIMAL(11,8) NULL` | Destination Longitude |
| `distance_km` | `DECIMAL(5,2)` | Calculated driving distance |
| `total_harga` | `DECIMAL(10,2)` | Total fare calculated |
| `catatan` | `TEXT` | Order notes/instructions |
| `metode_bayar` | `ENUM('qris', 'cash')` | Payment method chosen |
| `snap_token` | `VARCHAR(255) NULL` | Midtrans Snap token |
| `status` | `VARCHAR(50)` | Status (`Menunggu Pembayaran`, `Menunggu Driver`, etc.) |
| `payment_status` | `ENUM('pending', 'paid', 'failed', 'cod')` | Payment verification status |

---

## 5. Technical Architecture & Component Flow

```
[User Types Location] ──► [Google Places Autocomplete] ──► Select Precise Location
                                                                   │
                                                                   ▼
[Calculate Driving Route] ◄── [Google Directions Service] ◄── [Plot Map Markers]
          │
          ▼
[Update Interactive Map Polyline & Pan to Bounds]
          │
          ▼
[Calculate Exact Distance (KM) & Render Ongkir]
          │
          ▼
[User Submits Form]
          │
          ├─── (Metode: Cash / COD) ──► Insert DB (payment_status = 'cod') ──► Redirect to Pesanan Saya
          │
          └─── (Metode: QRIS / Midtrans) 
                     │
                     ▼
             [Call Midtrans Snap API via Server Key]
                     │
                     ▼
             [Receive snap_token & Open snap.pay() Modal]
                     │
                     ├───► [Payment Completed] ──► Webhook Updates payment_status = 'paid'
                     └───► [User Redirected to pesanan_saya.php]
```

---

## 6. Implementation Roadmap

1. **Phase 1: Configuration & Credentials Setup**
   * Insert valid Midtrans Sandbox Server Key & Client Key in `config.php`.
   * Enable Google Places API & Directions API on Google Cloud Console.
2. **Phase 2: Frontend Interactive Map UI Update (`order.php`)**
   * Add Google Places Autocomplete to location input elements.
   * Add interactive `<div id="map"></div>` container with custom styling.
   * Add Directions Renderer script for route animation and distance feedback.
3. **Phase 3: Midtrans Payment Handshake**
   * Implement AJAX handler in `order.php` for generating Midtrans Snap Token.
   * Include Midtrans Snap JS library (`https://app.sandbox.midtrans.com/snap/snap.js`).
   * Bind `snap.pay()` popup modal trigger to submission event.
4. **Phase 4: Webhook & Order State Synchronization**
   * Create `midtrans_notification.php` endpoint to handle status updates.
