# Pharmacy Kiosk

A self-service pharmacy kiosk demo built on Laravel 13, Blade and Alpine.js. Customers search medicines, fill a basket and leave with a barcoded order slip to pay at the counter. Prescription medicines are gated on the member's purchase history, and an AI stock assistant suggests same-ingredient brands when something is out of stock.

## Run it

```bash
composer install
npm install
cp .env.example .env        # then set KIOSK_ADMIN_PASSWORD (or let the seeder generate one)
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Open http://localhost:8000. Re-run `php artisan migrate:fresh --seed` to reset stock and orders between demos.

## Demo script

| Try this | What happens |
| --- | --- |
| Add **Motrin IB** as a guest | Over-the-counter, goes straight in |
| Add **Amoxil** as a guest | Blocked: "Prescription required. Please proceed to the pharmacy counter for assistance." |
| **Member sign in** → Maria Santos (100245) | Mock card tap. A pending prescription item is retried automatically |
| Maria adds **Glucophage** | Allowed, she has bought it before |
| Maria adds **Amoxil** or **Azithromycin** | Still blocked, she never has |
| **Find alternatives** on Advil | Motrin IB (same 200 mg tablet) first, Nurofen flagged as a different form |
| Ask the assistant about **Claritin** | Loratadine brands only. Zyrtec and Allegra treat allergies too, but have a different ingredient, so they never appear |
| Ask about **Lipitor** | Suggests Sandoz atorvastatin 20 mg, which Maria can add because she has filled Lipitor 20 mg |
| Ask "what can I take for a headache?" | Declined: the assistant only finds alternatives for out-of-stock items |
| **Finish** → **Place order** | Order slip with queue number and Code 128 barcode. The session is wiped and the screen returns to "Touch to begin" after 30 seconds |
| Leave the shopping screen alone for 90 seconds | "Are you still there?" then an automatic reset |

Daniel Okafor (100318) has Ventolin on file instead, so his and Maria's prescriptions don't overlap.

## Staff area (hidden)

Nothing on the kiosk links to it. Open `/staff-portal/login` (set `KIOSK_ADMIN_PATH` to change it), or tap the logo on the "Touch to begin" screen five times quickly. One tap on the logo still starts a session.

- The seeded admin uses `KIOSK_ADMIN_EMAIL` / `KIOSK_ADMIN_PASSWORD` from `.env`.
- Add or reset admins with `php artisan kiosk:admin someone@example.com --name="Their Name"`. There is no public register page.
- Staff use a separate `admin` guard and `admins` table. Members sign in by tapping a card with no password, so a card can never open the staff area.
- Login is limited to 5 attempts a minute. "Touch to begin" and "Start over" sign staff out too, since the kiosk is a shared device.

The dashboard lists kiosk orders awaiting payment, with what to fetch and a flag on prescription items, plus stock alerts. For each order:

- **Mark paid** completes it. From then on it counts as purchase history, so its prescription items become refillable at the kiosk.
- **Cancel** asks for confirmation, then returns the stock that checkout reserved.

Both only act on an order that is still awaiting payment, checked in the same database update, so two tills can't handle the same slip twice.

## How the stock assistant stays in scope

`app/Services/Assistant/PharmacyAssistant.php` enforces the rule in code. The model is never trusted to follow it on its own:

1. **The database decides what the customer means.** Typed text is matched against brand and active-ingredient names. Symptom questions match nothing and get a fixed reply. The customer's text is never sent to the model, so it can't be used to inject instructions.
2. **The database picks the candidates.** `Medicine::alternativesTo()` returns in-stock medicines with the same active ingredient (case-insensitive exact match) and a different brand, ordered by same strength, then same dosage form, then price.
3. **The model only ranks the candidates and writes the message.** The request uses structured output, and its JSON schema limits `alternative_ids` to an `enum` of the candidate ids.
4. **The reply is checked.** Ids outside the candidates are dropped. A message that names any other brand or ingredient in the catalogue is discarded, and the template message is used instead. A refusal, an API error or a timeout also falls back to the template.

With no `ANTHROPIC_API_KEY`, steps 1, 2 and 4 run unchanged and a template writes the message, so the demo works offline. Set the key to have Claude (`claude-opus-5-5` at low effort, with server-side refusal fallback) write it. The add-to-basket button still goes through the prescription check, so the assistant can suggest a prescription medicine but can't hand it out.

## Configuration

`config/kiosk.php`: pharmacy name, currency (an ISO code such as `USD` or `PHP`, set with `KIOSK_CURRENCY`), idle and receipt timers, the per-item limit, the staff path, the assistant model, and `prescription_match`:

- `ingredient` (default): any completed purchase of the same active ingredient and strength counts, so a Lipitor 20 mg customer can refill with generic atorvastatin 20 mg.
- `product`: only the exact product counts.

## How a request flows

It works like a classic Laravel CRUD app: every button is a form posting to a named route. The controller validates, does the work, and redirects back with a flash message. Nothing goes through JavaScript.

```
tap "Add to basket"
  → POST /kiosk/basket/{medicine}       (form with @csrf, route model binding)
  → BasketController@store              (policy check, Cart::add)
  → redirect back to /kiosk?q=…#medicine-5 with('success', …)
  → kiosk.blade.php shows the toast and the updated basket
```

| Method | URL | Route name | Controller | What it does |
| --- | --- | --- | --- | --- |
| GET | `/` | `welcome` | `KioskController@welcome` | "Touch to begin" |
| POST | `/start` | `kiosk.start` | `KioskController@start` | Fresh session, go to the shop |
| GET | `/kiosk?q=&type=&class=` | `kiosk` | `KioskController@index` | Search, browse, basket, assistant |
| POST | `/reset` | `kiosk.reset` | `KioskController@reset` | Start over / idle timeout |
| POST | `/kiosk/basket/{medicine}` | `basket.store` | `BasketController@store` | Add (prescription check first) |
| PUT | `/kiosk/basket/{medicine}` | `basket.update` | `BasketController@update` | Change quantity |
| DELETE | `/kiosk/basket/{medicine}` | `basket.destroy` | `BasketController@destroy` | Remove |
| POST | `/kiosk/member` | `member.store` | `MemberController@store` | Mock card sign in |
| DELETE | `/kiosk/member` | `member.destroy` | `MemberController@destroy` | Sign out |
| POST | `/kiosk/assistant` | `assistant.store` | `AssistantController@store` | Ask the stock assistant |
| POST | `/kiosk/checkout` | `checkout.store` | `CheckoutController@store` | Create the order, wipe the session |
| GET | `/receipt/{order}` (signed) | `receipt` | `ReceiptController@show` | Order slip with barcode |

The staff routes live under `/staff-portal` (see above). Run `php artisan route:list` to see them all.

The drawers and popups on `/kiosk` are opened from flash data: `panel` (assistant, basket, member) and `prescription_required`. Alpine only shows and hides them. `resources/js/app.js` holds the touch-screen helpers: the idle timer, the on-screen keyboard, and double-tap protection.

## Where things are

| Area | Files |
| --- | --- |
| Routes | `routes/web.php` |
| Schema and demo data | `database/migrations`, `database/seeders` |
| Models and scopes | `app/Models` (`Medicine::alternativesTo`, `User::hasPurchasedBefore`) |
| Prescription rule | `app/Policies/MedicinePolicy.php`, re-checked inside checkout |
| Basket and assistant chat | `app/Services/Cart.php`, `app/Services/Assistant/Conversation.php` (both kept in the session) |
| Controllers | `app/Http/Controllers` (kiosk, basket, member, assistant, checkout, receipt, `Admin/*`) |
| Views | `resources/views/welcome`, `kiosk`, `receipt`, `admin/*`, components such as `medicine-card`, `alert`, `input-field` |
| Front end | `resources/js/app.js` (touch helpers), `resources/css/app.css` (design tokens) |

## Tests

```bash
php artisan test
```

52 feature tests cover the prescription rules, stock limits, checkout and session reset, signed receipts, the staff login, paying and cancelling orders, and the assistant's guardrails. The Claude path is exercised through the real SDK with a mocked HTTP transport.
