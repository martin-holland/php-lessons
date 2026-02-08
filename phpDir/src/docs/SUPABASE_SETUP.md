# Supabase + PHP Authentication Demo

This guide will walk you through setting up Supabase with PHP, including Google Authentication.

---

## Part 1: Setting Up Supabase

### Step 1: Create a Supabase Account
1. Go to [https://supabase.com](https://supabase.com)
2. Click "Start your project" and sign up (you can use GitHub)
3. Create a new project:
   - Give it a name (e.g., "php-auth-demo")
   - Set a strong database password (save this!)
   - Choose a region close to you
   - Click "Create new project"

### Step 2: Get Your API Keys
Once your project is created:
1. Go to **Settings** (gear icon) → **API**
2. You'll need TWO values:
   - **Project URL**: `https://xxxxx.supabase.co`
   - **anon (public) key**: A long string starting with `eyJ...`

> **IMPORTANT**: Never share your `service_role` key publicly. The `anon` key is safe for client-side use.

---

## Part 2: Setting Up Google Authentication

### Step 1: Create Google OAuth Credentials
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Go to **APIs & Services** → **Credentials**
4. Click **+ CREATE CREDENTIALS** → **OAuth client ID**
5. If prompted, configure the OAuth consent screen:
   - Choose "External" user type
   - Fill in app name and your email
   - Add scopes: `email` and `profile`
   - Add your email as a test user
6. Back in Credentials, create OAuth client ID:
   - Application type: **Web application**
   - Name: "Supabase Auth"
   - **Authorized redirect URIs**: Add your Supabase callback URL:
     ```
     https://YOUR-PROJECT-REF.supabase.co/auth/v1/callback
     ```
   - Click **Create**
7. Copy the **Client ID** and **Client Secret**

### Step 2: Configure Google in Supabase
1. In Supabase Dashboard, go to **Authentication** → **Providers**
2. Find **Google** and click to enable it
3. Paste your **Client ID** and **Client Secret**
4. Click **Save**

---

## Part 3: Create the Database Table

Run this SQL in Supabase (go to **SQL Editor** → **New query**):

```sql
-- Create a simple user_notes table
-- Each user can only see their own notes (Row Level Security)

CREATE TABLE user_notes (
    id SERIAL PRIMARY KEY,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Enable Row Level Security (RLS)
ALTER TABLE user_notes ENABLE ROW LEVEL SECURITY;

-- Policy: Users can only see their own notes
CREATE POLICY "Users can view own notes" ON user_notes
    FOR SELECT USING (auth.uid() = user_id);

-- Policy: Users can insert their own notes
CREATE POLICY "Users can insert own notes" ON user_notes
    FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Policy: Users can update their own notes
CREATE POLICY "Users can update own notes" ON user_notes
    FOR UPDATE USING (auth.uid() = user_id);

-- Policy: Users can delete their own notes
CREATE POLICY "Users can delete own notes" ON user_notes
    FOR DELETE USING (auth.uid() = user_id);

-- Insert some sample data (we'll use a specific user_id later)
-- For now, this shows the table structure
```

### Understanding Row Level Security (RLS)
- **RLS** ensures users can only access their own data
- `auth.uid()` returns the currently logged-in user's ID
- Each policy defines what operations users can perform
- This is automatic - you don't need to filter by user_id in your code!

---

## Part 4: Set Up Your PHP Environment

### Step 1: Create the .env File
Copy `.env.example` to `.env` and fill in your values:

```
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_ANON_KEY=your-anon-key-here
```

### Step 2: File Structure
Your authentication demo uses these files:
```
phpDir/src/
├── 11-authentication.php    # Main demo page
├── auth/
│   ├── SupabaseAuth.php     # Supabase helper class
│   └── callback.php         # OAuth callback handler
├── .env                     # Your secret keys (don't commit!)
└── .env.example             # Example env file (safe to commit)
```

---

## Part 5: How It Works

### Authentication Flow
1. User clicks "Sign in with Google"
2. Browser redirects to Google's login page
3. After login, Google redirects back to Supabase
4. Supabase creates/updates the user and redirects to your callback
5. Your callback stores the session and redirects to the app
6. User is now logged in!

### Security Features
- **API keys in .env file**: Not in your code
- **Row Level Security**: Database-level protection
- **Session tokens**: Stored securely
- **HTTPS only**: All Supabase communication is encrypted

---

## Common Issues

### "Invalid redirect URI"
- Make sure your Supabase callback URL is in Google's authorized URIs
- The URL should be: `https://YOUR-PROJECT.supabase.co/auth/v1/callback`

### "User not allowed"
- Your app is probably in testing mode
- Add your email to test users in Google Cloud Console

### "CORS error"
- Make sure you're accessing your site via localhost, not file://

---

## Next Steps

After completing the setup:
1. Open `11-authentication.php` in your browser
2. Click "Sign in with Google"
3. Try creating some notes
4. Log out and back in - your notes persist!
5. Try with a different Google account - you'll see different notes

---

## Quick Reference

| Item | Where to Find It |
|------|------------------|
| Supabase URL | Settings → API |
| Supabase anon key | Settings → API |
| Google Client ID | Google Cloud Console → Credentials |
| Google Client Secret | Google Cloud Console → Credentials |
| Supabase callback URL | `https://[your-project].supabase.co/auth/v1/callback` |
