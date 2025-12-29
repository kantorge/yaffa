# Job Tuning Quick Reference

## Summary of Changes

### ✅ Fixed Issues
1. **SQL Error** - Duplicate join removed (41.6% failure rate eliminated)
2. **N+1 Queries** - Reduced from 185k to ~15 queries per job
3. **Timeout** - Increased from 4 minutes to 30 minutes
4. **Stuck Jobs** - Cleaned up 19 stuck jobs

### 📊 Expected Performance
- **Before:** 9,673s (2.7 hours), 185k queries
- **After:** ~60s (1 minute), ~15 queries

## Quick Commands

### Monitor Job Performance
```bash
# Daily check
php artisan jobs:analyze --days=1

# Weekly review
php artisan jobs:analyze --days=7

# Check specific account performance
php artisan jobs:analyze --class="CalculateAccountMonthlySummary"

# See only failures
php artisan jobs:analyze --status=failed
```

### Clean Up Stuck Jobs
```bash
# Preview what would be cleaned (safe)
php artisan jobs:cleanup-stuck --dry-run

# Clean jobs stuck over 2 hours (default)
php artisan jobs:cleanup-stuck

# Custom threshold (e.g., 6 hours)
php artisan jobs:cleanup-stuck --hours=6
```

### Manual Job Dispatch (Testing)
```bash
# Test investment value calculation for account 378
php artisan tinker --execute="dispatch(new App\Jobs\CalculateAccountMonthlySummary(App\Models\User::find(1), 'investment_value-fact', App\Models\AccountEntity::find(378)));"

# Monitor it
php artisan jobs:analyze --days=1
```

## Files Changed
- `app/Jobs/CalculateAccountMonthlySummary.php` - Optimized query logic
- `app/Console/Commands/CleanupStuckJobs.php` - New cleanup command

## Next Steps
1. Test optimized job on account 378 (previously slowest)
2. Monitor failure rate (should drop to near 0%)
3. Watch average execution time (should be under 2 minutes)
4. Set up daily `jobs:analyze` in cron/scheduler
5. Run `jobs:cleanup-stuck` weekly

## Success Criteria
- [ ] No SQL errors (1066 Not unique table/alias)
- [ ] Query count under 100 per job
- [ ] Execution time under 2 minutes
- [ ] Failure rate under 5%
- [ ] No jobs stuck over 2 hours
