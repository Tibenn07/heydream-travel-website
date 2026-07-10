<?php
// Google Maps API key, used to embed a live map (via the Maps Embed API) that
// geocodes a partner's address and drops a pin automatically.
//
// Get a key at https://console.cloud.google.com/google/maps-apis
//   1. Create/select a project, enable the "Maps Embed API".
//   2. Create an API key under APIs & Services > Credentials.
//   3. Restrict it to your domain(s) (HTTP referrer restriction) before going live.
//
// Leave this blank to fall back to a free, keyless map embed instead.
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');
