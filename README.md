# Pharmacy Kiosk

A self-service pharmacy kiosk demo built on Laravel 13, Blade and Alpine.js, set up for a Philippine drugstore: local brands such as Biogesic, Neozep Forte and RiteMed generics, prices in pesos (₱), and a red-and-white design. Customers search medicines, see in plain words what each one is for, count how many **strips and boxes** they want with +/- buttons, fill a basket and leave with a barcoded order slip to pay at the counter. Prescription medicines are gated on the member's purchase history, and an AI stock assistant suggests same-ingredient brands when something is out of stock and answers questions about them, such as "are they the same dosage?".

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
| Add **Biogesic** as a guest | Over-the-counter. A popup shows what it's for and two +/- counters: **Strips** (₱50.00, 10 tablets) and **Boxes** (₱480.00, 10 strips, "Save ₱20.00 a box"). Tap + three times on strips and once on boxes, and "Add to basket" adds 3 strips and 1 box in one go, with the running total shown |
| Add **Ascof Lagundi** or **Medicol Advance** | Only a few strips are left, so the box counter can't go above 0: "not enough for a box" |
| Count 4 boxes and 1 strip of **Solmux** | Refused as a whole, nothing added: the 40 strips on the shelf cover 4 boxes but not the extra strip |
| Add **Amoxil** as a guest | Blocked before the strip/box question: "Prescription required. Please proceed to the pharmacy counter for assistance." |
| **Member sign in** → Maria Santos (100245) | Mock card tap. A pending prescription item is retried automatically |
| Maria adds **Glucophage** | Allowed, she has bought it before |
| Maria adds **Amoxil** or **RiteMed Co-Amoxiclav** | Still blocked, she never has |
| **Find alternatives** on Advil | Medicol Advance (same 200 mg softgel) first, then RiteMed Ibuprofen flagged as a different form. Alaxan FR never appears: it adds paracetamol |
| **Find alternatives** on Bioflu | Neozep Forte, which has the same three ingredients at the same strengths |
| Ask the assistant about **Claritin** | Loratadine brands only (RiteMed Loratadine, Allerta). Virlix treats allergies too, but has a different ingredient, so it never appears |
| Then tap **Are they the same dosage?** | "Yes. RiteMed Loratadine and Allerta have the same dose as Claritin: loratadine 10 mg in each tablet." The tap-to-ask row also offers **Which one is cheaper?**, **Do they need a prescription?**, **What is it for?** and **How many are in a strip and a box?** |
| Type "Is Virlix the same?" | "Virlix is a different medicine: it has cetirizine, not loratadine." |
| Type "is neozip the same as bioflu?" | Small typos are forgiven: Neozep Forte is the same medicine as Bioflu, only the brand and price differ |
| Ask about **Lipitor** | Suggests RiteMed Atorvastatin 20 mg, which Maria can add because she has filled Lipitor 20 mg |
| Ask "what can I take for a headache?" or "how many should I take?" | Declined: what to take and how much is for the pharmacist |
| **Finish** → **Place order** | Order slip with queue number, Code 128 barcode, and strip or box on every line. The session is wiped and the screen returns to "Touch to begin" after 30 seconds |
| Leave the shopping screen alone for 90 seconds | "Are you still there?" then an automatic reset |

Daniel Okafor (100318) has Cozaar (losartan) on file instead, so his and Maria's prescriptions don't overlap.

## The catalogue

`database/seeders/MedicineSeeder.php` holds 36 medicines sold in Philippine drugstores, in Pain & Fever, Cough & Colds, Allergy, Stomach & Digestion, Vitamins and four prescription classes. Prices are sample figures in pesos for the demo, not a price list, so set real ones before using it in a store.

**Prescription only (Rx):** antibiotics (Amoxil, Augmentin and their RiteMed generics), blood pressure (Norvasc, Cozaar), cholesterol (Lipitor) and diabetes (Glucophage) medicines, with a RiteMed generic for each. The Philippine FDA registers these drugs as "Prescription Drug (Rx)". Everything else, including Biogesic, Neozep Forte, Bioflu, Alaxan FR, Dolfenal, Solmux, Diatabs and Kremil-S, is over the counter. Have a pharmacist confirm the list against the FDA verification portal (verification.fda.gov.ph) before going live.

**Strip or box:** every medicine comes in blister strips. `pieces_per_strip`, `strips_per_box`, `strip_price` and `box_price` are set per medicine, and **stock is counted in strips**. A box takes `strips_per_box` strips off the shelf, so it can only be chosen while that many are left. A customer can have the same medicine by the strip and by the box at once, and both draw on the same stock. Each order line records the packaging and the strips it took, and cancelling an order puts exactly those strips back.

**What it's for:** each medicine has a `description`, one or two plain sentences such as "For fever and mild to moderate pain, such as headache, toothache, body aches and menstrual cramps." It is shown on the card, in the strip/box popup and by the assistant. The seeder writes it once per active ingredient (`MedicineSeeder::USES`), so every brand of the same medicine says the same thing. They are label-style uses only, never dosing advice.

**Pictures:** each card shows a drawing of the pack (a box in the brand's colour and a blister strip with the right number and shape of pills, marked "Rx" for prescription items). To use a real product photo instead, put the image under `public/` (for example `public/images/medicines/biogesic.webp`) and set that medicine's `image_path` to `images/medicines/biogesic.webp`. Use photos you have the rights to, such as your own shots or supplier-provided images.

## Staff area (hidden)

Nothing on the kiosk links to it. There are two ways in:

- **On the kiosk itself:** double-tap the logo on the "Touch to begin" screen (a double click with a mouse). A kiosk browser runs full screen with no address bar, so this is the only way in there. One tap on the logo still starts a session.
- **From another device**, such as the counter PC or a staff tablet on the same network: open `/staff-portal/login` on the kiosk server's address. Set `KIOSK_ADMIN_PATH` to change the path.

On the kiosk, tapping the email or password field opens the on-screen keyboard. It has Shift (for one capital letter) and a row of symbols, so staff don't need a physical keyboard. "Go" moves from email to password, then logs in. Passwords can only use the letters, numbers and symbols on that keyboard.

The login page and the dashboard use the same "Are you still there?" timer as the shopping screen. If nobody answers, staff are logged out and the kiosk goes back to "Touch to begin", so a customer can't use a dashboard someone left open. **Log out** also goes straight back to "Touch to begin".

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

1. **The database decides what the customer means.** Typed text is matched against product, brand and active-ingredient names, allowing a one- or two-letter typo. Questions about what to take, how much, or whether it is safe (`Topic::ADVICE`), and symptom questions that name no medicine, get a fixed reply. A follow-up that names nothing, like "are they the same dosage?", is about the medicines the last reply was about (`Conversation::context()`). The customer's text is never sent to the model, so it can't be used to inject instructions.
2. **The database picks the candidates.** `Medicine::alternativesTo()` returns in-stock medicines with the same active ingredient (case-insensitive exact match) and a different brand, ordered by same strength, then same dosage form, then strip price.
3. **The model only ranks the candidates and writes the message.** The request uses structured output, and its JSON schema limits `alternative_ids` to an `enum` of the candidate ids.
4. **The reply is checked.** Ids outside the candidates are dropped. A message that names any other brand or ingredient in the catalogue is discarded, and the template message is used instead. Combination products are split into their ingredients first, so the paracetamol in Neozep Forte doesn't count as naming Biogesic. A refusal, an API error or a timeout also falls back to the template.

5. **Questions are answered from facts, not by the model.** `Topic` recognises what a question asks (price, prescription, dose, pack size, same or different, what it's for, in English and a few Filipino words such as "magkano" and "reseta"). `FollowUp` then writes the answer from the catalogue's ingredients, strength, dosage form, prices, prescription flag and description. A different strength or form always comes with "please check with our pharmacist before switching".

The drawer offers tap-to-ask buttons for these follow-ups, starter questions built from today's out-of-stock list, and a large typed-question box. Replies are announced to screen readers. The header button is always labelled "Ask", and a banner above the catalogue points customers to it.

With no `ANTHROPIC_API_KEY`, steps 1, 2, 4 and 5 run unchanged and a template writes the suggestion, so the demo works offline. Set the key to have Claude (`claude-opus-5-5` at low effort, with server-side refusal fallback) write it. The add-to-basket button still goes through the prescription check, so the assistant can suggest a prescription medicine but can't hand it out.

## Configuration

`config/kiosk.php`: pharmacy name, currency (an ISO code, `PHP` by default, set with `KIOSK_CURRENCY`), idle and receipt timers, the per-item limit (strips or boxes per line), the staff path, the assistant model, and `prescription_match`:

- `ingredient` (default): any completed purchase of the same active ingredient and strength counts, so a Lipitor 20 mg customer can refill with RiteMed atorvastatin 20 mg.
- `product`: only the exact product counts.

## How a request flows

It works like a classic Laravel CRUD app: every button is a form posting to a named route. The controller validates, does the work, and redirects back with a flash message. Nothing goes through JavaScript.

```
tap "Add to basket", then "By the box"
  → POST /kiosk/basket/{medicine}       (form with @csrf, route model binding, packaging=box)
  → BasketController@store              (policy check, packaging validated, Cart::add)
  → redirect back to /kiosk?q=…#medicine-5 with('success', …)
  → kiosk.blade.php shows the toast and the updated basket
```

| Method | URL | Route name | Controller | What it does |
| --- | --- | --- | --- | --- |
| GET | `/` | `welcome` | `KioskController@welcome` | "Touch to begin" |
| POST | `/start` | `kiosk.start` | `KioskController@start` | Fresh session, go to the shop |
| GET | `/kiosk?q=&type=&class=` | `kiosk` | `KioskController@index` | Search, browse, basket, assistant |
| POST | `/reset` | `kiosk.reset` | `KioskController@reset` | Start over / idle timeout |
| POST | `/kiosk/basket/{medicine}` | `basket.store` | `BasketController@store` | Add strips and boxes, `quantities[strip]` and `quantities[box]` (prescription check first) |
| PUT | `/kiosk/basket/{medicine}` | `basket.update` | `BasketController@update` | Change how many strips or boxes |
| DELETE | `/kiosk/basket/{medicine}` | `basket.destroy` | `BasketController@destroy` | Remove the strips or the boxes |
| POST | `/kiosk/member` | `member.store` | `MemberController@store` | Mock card sign in |
| DELETE | `/kiosk/member` | `member.destroy` | `MemberController@destroy` | Sign out |
| POST | `/kiosk/assistant` | `assistant.store` | `AssistantController@store` | Ask the stock assistant |
| POST | `/kiosk/checkout` | `checkout.store` | `CheckoutController@store` | Create the order, wipe the session |
| GET | `/receipt/{order}` (signed) | `receipt` | `ReceiptController@show` | Order slip with barcode |

The staff routes live under `/staff-portal` (see above). Run `php artisan route:list` to see them all.

The drawers and popups on `/kiosk` are opened from flash data: `panel` (assistant, basket, member) and `prescription_required`. The strip-or-box popup opens straight from the card through Alpine's `pack` state. Its +/- counters (`packCounter` in `app.js`) only fill in an ordinary form, which the controller checks again. Alpine only shows and hides them. `resources/js/app.js` holds the touch-screen helpers: the idle timer, the on-screen keyboard, and double-tap protection.

## Where things are

| Area | Files |
| --- | --- |
| Routes | `routes/web.php` |
| Schema and demo data | `database/migrations`, `database/seeders` |
| Models and scopes | `app/Models` (`Medicine::alternativesTo`, `User::hasPurchasedBefore`) |
| Prescription rule | `app/Policies/MedicinePolicy.php`, re-checked inside checkout |
| Basket and assistant chat | `app/Services/Cart.php`, `app/Services/Assistant/Conversation.php` (both kept in the session) |
| Controllers | `app/Http/Controllers` (kiosk, basket, member, assistant, checkout, receipt, `Admin/*`) |
| Views | `resources/views/welcome`, `kiosk`, `receipt`, `admin/*`, components such as `medicine-card`, `medicine-picture` (the drawn pack), `packaging-chooser` (the strip-and-box counters), `stock-assistant` (the assistant drawer), `alert`, `input-field` |
| Front end | `resources/js/app.js` (touch helpers), `resources/css/app.css` (design tokens) |

## Tests

```bash
php artisan test
```

84 feature tests cover the prescription rules, strip and box pricing and counting both in one tap, plain-language descriptions, stock limits (counted in strips), checkout and session reset, signed receipts, the staff login, paying and cancelling orders, and the assistant's guardrails and follow-up answers. The Claude path is exercised through the real SDK with a mocked HTTP transport.
