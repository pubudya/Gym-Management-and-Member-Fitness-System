# Gym Management and Member Fitness System

This is a gym management and member fitness system. Only **Admin**, **Coach** and **Member** accounts can log in.

To put this online for gym owners (phone and laptop), use **Railway** (PHP + MySQL). Do **not** use Netlify. Click-by-click steps: [RAILWAY.md](RAILWAY.md).

**Admin** has complete access to the gym. Admin can register, edit, deactivate and search members. Admin can register personal coaches and give them a login, and assign members to coaches. Admin can set the monthly gym fee, assign or renew monthly memberships, record online and offline payments, receipts, attendance, trainers/coaches, workout plans, meal plans, fitness goals and progress, classes, appointments, equipment, inventory, income and expenses, reports, feedback, announcements, notifications and audit logs. Admin can create workout plans and meal plans for **all members**.

**Admin** has complete access to the gym. Admin can register, edit, deactivate and search members. Admin can register personal coaches and give them a login, and assign members to coaches. Admin can set the monthly gym fee, assign or renew monthly memberships, record online and offline payments, receipts, attendance, trainers/coaches, workout plans, meal plans, fitness goals and progress, classes, appointments, equipment, inventory, income and expenses, reports, feedback, announcements, notifications and audit logs. Admin can create workout plans and meal plans for **all members**.

**Coach** only has a coach login. Coaches cannot use admin menus. A coach can only see **assigned members**. They can track that member’s progress and gains, and create clear workout routines and meal plans for those members only.

**Member** can log in to their own dashboard, update permitted profile details, view membership and payments, check in and check out, see their assigned coach, follow assigned workout plans and meal plans, log workout progress, book classes, view appointments, submit feedback and receive notifications.

Workout plans show the type of exercise (strength, pilates, yoga, HIIT, cardio and others), how to do it (sets and reps like **10 × 3**, or time duration / hold), rest time, and a photo for each exercise. Meal plans show breakfast, lunch, dinner and snacks with photos, portion and calories.

The system also keeps SMS notifications in MySQL and can send them through a PHP SMS API. Automatic rules cover membership expiry, outstanding payments, inactive members, low stock, maintenance, class capacity and gym occupancy.

---

## Admin menu

- Dashboard
- Capacity & peaks
- Members
- Memberships
- Coaches
- Workout plans
- Meal plans
- Progress & goals
- Attendance
- Classes
- Appointments
- Payments
- Expenses
- Equipment
- Inventory
- Reports
- Feedback
- Notifications
- Audit logs
- Settings

Admin can register coaches, assign members to coaches, and create workouts and meals for every member.

## Coach menu

- Dashboard
- Assigned members
- Member progress
- Workout plans
- Meal plans

Coach can only create workout routines and meal plans for assigned members. Coach can track assigned member progress, gains, workouts and meals. Coach cannot open admin pages.

## Member menu

- Dashboard
- My profile
- Membership
- Payments
- Attendance
- My workouts
- Meal plan
- Classes
- Appointments
- Feedback
- Notifications

Member can see their monthly fee, pay, check in with QR or member code, follow the assigned workout (with photos and 10 × 3 or timed exercises), follow the meal plan with photos, and send feedback.

---

## User manual

Create the gym owner account during install, then sign in as **Admin**. Register coaches and members from the admin console. Change the default admin password immediately.

### How Admin uses the system

1. Go to **Members** to register a new member (name, NIC, phone, email, password). Search and edit from the same page. Deactivate if the member should not log in.
2. Go to **Settings** and set the **Monthly gym fee**. Only admin can change this amount. Then go to **Memberships** to assign or renew a member for one month and record the amount paid.
3. Go to **Coaches** to register a personal coach. Enter their name, phone, username and password so they can log in. Assign members to that coach. The coach can only train those members.
4. Go to **Workout plans** to create a program for any member. Add exercises with type (pilates, strength, yoga, HIIT, cardio), day, and how to track it:
   - sets and reps, shown like **10 × 3**
   - time duration
   - hold time  
   Upload a photo for the exercise. Then assign the plan to a member.
5. Go to **Meal plans** to create breakfast, lunch, dinner and snack items with photos, portion and calories. Assign the meal plan to any member.
6. Go to **Progress & goals** to log weight and body measurements and set fitness goals.
7. Go to **Attendance** and type or scan the member code / QR to check the member in or out.
8. Use **Payments** for cash/card payments, verify online payments, and print receipts. Use **Expenses** for gym bills.
9. Use **Classes** and **Appointments** to schedule group classes and trainer sessions. Class booking stops or goes to waiting list when the class is full.
10. Use **Equipment** and **Inventory** for machines, maintenance, stock in and stock out. Low stock is flagged.
11. Use **Reports** to filter, print or export CSV. Use **Notifications** to send announcements. Use **Feedback** to reply to members. Use **Audit logs** to see who did what. Use **Settings** for gym name, capacity and reminder days.
12. On **Dashboard**, click **Run automation** to mark expired memberships, send expiry/payment reminders, and alert low stock / maintenance / full gym.

### How Coach uses the system

1. Log in as coach. You only see assigned members. You cannot open admin pages.
2. **Dashboard** shows how many members you have and their latest workout logs.
3. **Assigned members** shows each member’s last visit, weight change and engagement. Open **Progress** to see charts, goals, assigned workouts and meals.
4. **Workout plans** — create a plan, add exercises with photos and 10 × 3 or timed work, then assign it only to your members. If nobody is assigned to you yet, ask Admin.
5. **Meal plans** — create meals with photos and assign them only to your members.

### How Member uses the system

1. Log in as member.
2. **Dashboard** shows your membership status, expiry, balance, coach, QR code and check-in button.
3. **My profile** — update phone, email, address, password and photo. NIC and member code are managed by admin.
4. **Membership** — see start date, end date and balance. Pay the monthly gym fee set by the gym owner.
5. **Payments** — pay online (demo GymPay), see history, outstanding balance and print receipts.
6. **Attendance** — check in or check out, or show your QR / member code at the desk.
7. **My workouts** — see each exercise photo and the prescription (10 × 3 or time). Log the sets, reps, weight or minutes you completed.
8. **Meal plan** — see breakfast, lunch, dinner and snacks with photos.
9. **Classes** — book a class if there is a free spot. **Appointments** shows sessions booked by staff.
10. **Feedback** — send a rating or a complaint. **Notifications** shows SMS and system messages.

If login fails, the account may be inactive. Ask Admin to activate it. Coaches who have no members yet must wait until Admin assigns members.
