#!/bin/bash

echo "🧪 Testing Bulk Upload Functionality"
echo "===================================="

# Check if Laravel is running
if ! curl -s http://localhost:8000 > /dev/null; then
    echo "❌ Laravel server is not running on port 8000"
    echo "   Start it with: php artisan serve"
    echo ""
    echo "💡 To start the server:"
    echo "   cd /Users/legacy/Projects/Backend/laravel/smart-product-uploader"
    echo "   php artisan serve --host=0.0.0.0 --port=8000"
    exit 1
fi

echo "✅ Laravel server is running"

# Check queue status
echo ""
echo "📊 Checking queue status..."
php artisan queue:status

# Check if there are pending jobs
PENDING_JOBS=$(php artisan queue:status 2>/dev/null | grep "Pending jobs:" | awk '{print $3}' | tr -d '[:space:]')

if [ "$PENDING_JOBS" -gt 0 ]; then
    echo ""
    echo "⚠️  There are $PENDING_JOBS pending jobs"
    echo "   Processing them now..."
    
    for i in $(seq 1 $PENDING_JOBS); do
        echo "   Processing job $i of $PENDING_JOBS..."
        php artisan queue:work --once --quiet
    done
    
    echo "✅ All pending jobs processed"
else
    echo "✅ No pending jobs"
fi

# Check if queue worker is running
if pgrep -f "queue:work" > /dev/null; then
    echo "✅ Queue worker is running"
else
    echo "⚠️  Queue worker is not running"
    echo "   Starting queue worker..."
    nohup php artisan queue:work --daemon > storage/logs/queue.log 2>&1 &
    echo "✅ Queue worker started in background"
fi

echo ""
echo "🎯 Ready to test bulk upload!"
echo ""
echo "📋 Test Steps:"
echo "1. Open http://localhost:8000/products in your browser"
echo "2. Click 'Bulk Upload' button"
echo "3. Upload the test file: test_bulk_upload.csv"
echo "4. Monitor the progress bar and status updates"
echo "5. Check the browser console for detailed logs"
echo ""
echo "📁 Test Files Available:"
echo "   - test_bulk_upload.csv (3 test products)"
echo "   - sample_products.csv (5 sample products)"
echo ""
echo "🔍 Troubleshooting:"
echo "   - Check browser console for JavaScript errors"
echo "   - Monitor Laravel logs: tail -f storage/logs/laravel.log"
echo "   - Check queue status: php artisan queue:status"
echo "   - Check queue worker: ps aux | grep 'queue:work'"
echo ""
echo "💡 Queue Worker Commands:"
echo "   Start: php artisan queue:work --daemon"
echo "   Stop: pkill -f 'queue:work'"
echo "   Status: php artisan queue:status"
echo "   Clear: php artisan queue:flush"
