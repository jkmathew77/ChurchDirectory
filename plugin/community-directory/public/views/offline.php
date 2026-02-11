<?php
/**
 * Offline fallback page for PWA.
 *
 * This page is cached by the service worker and displayed when the user
 * is offline and tries to navigate. Variables $app_name and $theme_color
 * are set by CD_PWA::serve_offline_page().
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Allow direct serving from service worker cache
    $app_name    = $app_name ?? 'St. Thekla Directory';
    $theme_color = $theme_color ?? '#8B0000';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $app_name ); ?> — Offline</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f8f9fa;
            color: #333;
            padding: 2rem;
        }
        .offline-card {
            text-align: center;
            max-width: 400px;
        }
        .offline-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            background: <?php echo esc_attr( $theme_color ); ?>1a;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .offline-icon svg {
            width: 40px;
            height: 40px;
            fill: <?php echo esc_attr( $theme_color ); ?>;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #1a1a1a;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .retry-btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: <?php echo esc_attr( $theme_color ); ?>;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .retry-btn:hover { opacity: 0.9; }
        .retry-btn:active { opacity: 0.8; }
        .app-name {
            font-size: 0.875rem;
            color: #999;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="offline-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4c-1.48 0-2.85.43-4.01 1.17l1.46 1.46C10.21 6.23 11.08 6 12 6c3.04 0 5.5 2.46 5.5 5.5v.5H19c1.66 0 3 1.34 3 3 0 1.13-.64 2.11-1.56 2.62l1.45 1.45C23.16 18.16 24 16.68 24 15c0-2.64-2.05-4.78-4.65-4.96zM3 5.27l2.75 2.74C2.56 8.15 0 10.77 0 14c0 3.31 2.69 6 6 6h11.73l2 2 1.27-1.27L4.27 4 3 5.27zM7.73 10l8 8H6c-2.21 0-4-1.79-4-4s1.79-4 4-4h1.73z"/>
            </svg>
        </div>
        <h1>You're Offline</h1>
        <p>It looks like you've lost your internet connection. Please check your connection and try again.</p>
        <button class="retry-btn" onclick="location.reload()">Try Again</button>
        <div class="app-name"><?php echo esc_html( $app_name ); ?></div>
    </div>
</body>
</html>
