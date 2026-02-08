-- ============================================
-- SUPABASE DATABASE SETUP
-- Run this SQL in your Supabase SQL Editor
-- ============================================

-- Step 1: Create the user_notes table
-- This table stores notes for each user
CREATE TABLE IF NOT EXISTS user_notes (
    id SERIAL PRIMARY KEY,                                    -- Auto-incrementing ID
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE, -- Links to Supabase auth
    title VARCHAR(255) NOT NULL,                              -- Note title
    content TEXT,                                             -- Note content
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()         -- When created
);

-- Step 2: Enable Row Level Security (RLS)
-- This is CRUCIAL for security - without it, anyone can see all data!
ALTER TABLE user_notes ENABLE ROW LEVEL SECURITY;

-- Step 3: Create security policies
-- These policies ensure users can only access their OWN notes

-- Policy 1: Users can VIEW their own notes
CREATE POLICY "Users can view own notes" ON user_notes
    FOR SELECT
    USING (auth.uid() = user_id);

-- Policy 2: Users can INSERT their own notes
CREATE POLICY "Users can insert own notes" ON user_notes
    FOR INSERT
    WITH CHECK (auth.uid() = user_id);

-- Policy 3: Users can UPDATE their own notes
CREATE POLICY "Users can update own notes" ON user_notes
    FOR UPDATE
    USING (auth.uid() = user_id);

-- Policy 4: Users can DELETE their own notes
CREATE POLICY "Users can delete own notes" ON user_notes
    FOR DELETE
    USING (auth.uid() = user_id);

-- ============================================
-- VERIFICATION QUERIES
-- Run these to check your setup is correct
-- ============================================

-- Check the table was created:
-- SELECT * FROM user_notes;

-- Check RLS is enabled:
-- SELECT tablename, rowsecurity FROM pg_tables WHERE tablename = 'user_notes';

-- Check policies exist:
-- SELECT * FROM pg_policies WHERE tablename = 'user_notes';

-- ============================================
-- HOW ROW LEVEL SECURITY WORKS
-- ============================================
--
-- Without RLS: SELECT * FROM user_notes
--   -> Returns ALL notes from ALL users (security risk!)
--
-- With RLS: SELECT * FROM user_notes
--   -> Returns ONLY notes where user_id matches the logged-in user
--
-- The auth.uid() function automatically gets the current user's ID
-- from their authentication token. This happens at the database level,
-- so it's impossible to bypass through your application code.
--
-- ============================================
