# Setting Up Pusher Broadcasting

To use Pusher for real-time events in your SmartOrder application, follow these steps:

## 1. Update Your .env File

Add the following configurations to your `.env` file:

```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_APP_CLUSTER=your_pusher_cluster
```

## 2. Create a Pusher Account

If you don't have one already:
1. Go to https://pusher.com/ and sign up for an account
2. Create a new Channels app
3. Get your app credentials from the Pusher dashboard
4. Fill in the credentials in your .env file

## 3. Update Frontend Configuration

The frontend configuration in `resources/js/bootstrap.js` should match your Pusher credentials:

```javascript
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.VITE_PUSHER_APP_KEY,
    cluster: process.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true
});
```

## 4. Update Your Vite Environment Variables

Add these to your `.env` file:

```
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

## 5. Restart Your Application

Run these commands:

```bash
php artisan config:clear
php artisan cache:clear
npm run build
```

## 6. You're All Set!

Your application now uses Pusher for real-time events. No queue worker needed!

## Event Listeners

The following events are now broadcast in real-time:

- `NewOrderReceived`: When a new order is placed
- `OrderStatusChanged`: When an order status changes
- `ProductStockAlert`: When product stock is low or out

You can listen to these events in your frontend like this:

```javascript
// Listen for new orders
window.Echo.channel('orders')
    .listen('.NewOrderReceived', (e) => {
        console.log('New order received:', e.transaction);
        // Add your code to handle the new order
    });

// Listen for order status changes
window.Echo.channel('orders')
    .listen('.OrderStatusChanged', (e) => {
        console.log('Order status changed:', e);
        // Add your code to handle the status change
    });

// Listen for product stock alerts
window.Echo.channel('products')
    .listen('.ProductStockAlert', (e) => {
        console.log('Product stock alert:', e);
        // Add your code to handle the stock alert
    });
```