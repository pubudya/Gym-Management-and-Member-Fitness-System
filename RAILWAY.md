# Put this gym system on Railway

Railway is a website host that can run PHP and MySQL. Customers then open your Railway link on their phone or laptop. They do not install the app.

Do this once. After it works, you send gym owners the public link.

## 1. Put the code on GitHub

1. Create a GitHub account if you do not have one.
2. Upload this project as a GitHub repository.

## 2. Create the Railway project

1. Open [https://railway.app](https://railway.app) and sign in with GitHub.
2. Click **New project**.
3. Click **GitHub repo** and choose this gym project.
4. Railway will build using the `Dockerfile` in this folder.

## 3. Add MySQL (the database)

1. In the same Railway project click **Create** → **Database** → **MySQL**.
2. Open your **web** service (the gym app).
3. Open the **Variables** tab.
4. Click **Add variable reference** / **Connect** / share variables from the MySQL service so these appear:

- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLDATABASE`

Railway usually fills these for you when the MySQL service is linked. You do **not** type `127.0.0.1` or `root`.

## 4. Keep photos and receipts (volume)

Without this, member photos and payment receipts disappear when Railway restarts.

1. Open the **web** service.
2. Open **Settings** → **Volumes** (or **Storage**).
3. Add a volume.
4. Mount path must be exactly:

```text
/data
```

## 5. Set passwords

In the web service **Variables** tab, add:

| Name | Example | Why |
|---|---|---|
| `APP_SECRET` | a long random sentence | Login security |
| `ADMIN_PASSWORD` | your own strong password | First gym-owner login |
| `ADMIN_USERNAME` | `admin` | First username |
| `APP_BASE_URL` | leave empty | Railway uses the root URL |

Optional:

| Name | Value |
|---|---|
| `ADMIN_EMAIL` | `owner@yourgym.lk` |

## 6. Deploy and open the site

1. Wait until the web service is **Success**.
2. Open the web service → **Settings** → **Networking** → **Generate domain**.
3. You get a link like `https://something.up.railway.app`.
4. Open that link on your phone or laptop.
5. Sign in with `admin` and the `ADMIN_PASSWORD` you set.

The first start creates the database tables automatically. You should not need `install.php` on Railway.

Then go to **Settings** and set the gym name and monthly gym fee.

## 7. How gym owners and members use it

Send them the Railway link. They log in from Chrome / Safari. No Play Store app.

Use a **new Railway project + new MySQL + new volume** for each paying gym, so gyms cannot see each other’s data.

## 8. Daily expiry checks

You do not create a second cron service. The app already runs `cron/worker.php` in the background every hour. Expired software subscriptions are marked even if nobody logs in.

## If something fails

- **Cannot connect to MySQL:** the web service is not linked to MySQL. Add the `MYSQL*` variables.
- **Site opens then photos vanish:** the `/data` volume is missing.
- **404 / wrong CSS:** wait for deploy to finish, then hard-refresh.
- **Forgot admin password:** change `ADMIN_PASSWORD` only helps a **new empty** database. On an existing database, reset it in MySQL or from a working admin account.
