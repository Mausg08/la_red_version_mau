/**
 * UniLink — websocket-server/server.js
 * Real-time events using Socket.io + Redis adapter
 * Handles: moderation broadcasts, notifications, live feed
 */

const { createServer } = require('http');
const { Server }       = require('socket.io');
const { createClient } = require('redis');
const { createAdapter } = require('@socket.io/redis-adapter');
const jwt              = require('jsonwebtoken');

const PORT       = process.env.PORT       || 3001;
const JWT_SECRET = process.env.JWT_SECRET || 'CHANGE_THIS_IN_PRODUCTION';
const REDIS_URL  = process.env.REDIS_URL  || 'redis://localhost:6379';

// ---- HTTP server ----
const httpServer = createServer((req, res) => {
  res.writeHead(200, { 'Content-Type': 'text/plain' });
  res.end('UniLink WebSocket Server');
});

// ---- Socket.io ----
const io = new Server(httpServer, {
  cors: {
    origin: process.env.ALLOWED_ORIGINS?.split(',') || ['http://localhost', 'http://localhost:8080'],
    methods: ['GET', 'POST'],
    credentials: true,
  },
  pingTimeout: 30000,
  pingInterval: 10000,
});

// ---- Redis adapter (enables horizontal scaling) ----
async function setupRedis() {
  const pubClient = createClient({ url: REDIS_URL });
  const subClient = pubClient.duplicate();

  pubClient.on('error', err => console.error('[Redis pub]', err.message));
  subClient.on('error', err => console.error('[Redis sub]', err.message));

  await Promise.all([pubClient.connect(), subClient.connect()]);
  io.adapter(createAdapter(pubClient, subClient));
  console.log('[UniLink WS] Redis adapter connected');

  // Subscribe to PHP-side events (moderation actions, notifications)
  await subClient.subscribe('unilink:broadcast', (message) => {
    try {
      const event = JSON.parse(message);
      handleBroadcast(event);
    } catch (e) {
      console.error('[Redis sub] Invalid message:', e.message);
    }
  });
}

// ---- Auth middleware ----
io.use((socket, next) => {
  const token = socket.handshake.auth?.token || socket.handshake.headers?.authorization?.split(' ')[1];

  if (!token) {
    return next(new Error('Authentication required'));
  }

  try {
    const payload = jwt.verify(token, JWT_SECRET);
    socket.user   = payload;
    next();
  } catch (err) {
    next(new Error('Invalid or expired token'));
  }
});

// ---- Connection handler ----
io.on('connection', (socket) => {
  const user = socket.user;
  console.log(`[WS] Connected: user_id=${user.user_id} role=${user.role}`);

  // ---- Join rooms ----
  socket.on('join_room', ({ user_id, faculty, role }) => {
    // Personal room
    socket.join(`user:${user_id}`);

    // Faculty room
    if (faculty) socket.join(`faculty:${faculty}`);

    // Role rooms
    socket.join(`role:${role}`);
    if (role === 'moderator') socket.join('moderators');
    if (role === 'admin')     socket.join('admins');
    if (role === 'admin' || role === 'moderator') {
      socket.join(`mod_faculty:${faculty}`);
    }

    console.log(`[WS] user:${user_id} joined rooms: user, faculty:${faculty}, role:${role}`);
  });

  // ---- Moderator: moderate a post (broadcast to all users) ----
  socket.on('moderate_post', ({ post_id, reason }) => {
    if (!['admin', 'moderator'].includes(socket.user.role)) {
      return socket.emit('error', { message: 'Not authorized' });
    }
    // Broadcast to everyone — post disappears from all feeds
    io.emit('post_removed', { post_id, reason: reason || 'moderated' });
    console.log(`[WS] Post ${post_id} moderated by user:${socket.user.user_id}`);
  });

  // ---- User sends a like (broadcast count update to others viewing same post) ----
  socket.on('like_post', ({ post_id, likes_count }) => {
    socket.broadcast.emit('like_update', { post_id, likes_count });
  });

  // ---- Join group chat room ----
  socket.on('join_group', ({ group_id }) => {
    socket.join(`group:${group_id}`);
  });

  // ---- Group message ----
  socket.on('group_message', ({ group_id, message, sender_name }) => {
    io.to(`group:${group_id}`).emit('group_message', {
      group_id,
      message,
      sender_id:   socket.user.user_id,
      sender_name: sender_name || 'Usuario',
      timestamp:   new Date().toISOString(),
    });
  });

  // ---- Typing indicator ----
  socket.on('typing', ({ group_id }) => {
    socket.to(`group:${group_id}`).emit('user_typing', {
      user_id:   socket.user.user_id,
      group_id,
    });
  });

  socket.on('disconnect', (reason) => {
    console.log(`[WS] Disconnected: user_id=${user.user_id} reason=${reason}`);
  });

  socket.on('error', (err) => {
    console.error(`[WS] Socket error user:${user.user_id}:`, err.message);
  });
});

// ---- Handle broadcasts from PHP (via Redis pub/sub) ----
function handleBroadcast(event) {
  switch (event.type) {
    case 'notification':
      // Send to specific user
      io.to(`user:${event.user_id}`).emit('notification', event.data);
      break;

    case 'post_removed':
      io.emit('post_removed', event.data);
      break;

    case 'new_post':
      // Send to faculty room
      if (event.faculty_id) {
        io.to(`faculty:${event.faculty_id}`).emit('new_post', event.data);
      } else {
        io.emit('new_post', event.data);
      }
      break;

    case 'panic_alert':
      // Notify all moderators and admins
      io.to('moderators').to('admins').emit('panic_alert', event.data);
      io.to(`mod_faculty:${event.faculty_id}`).emit('panic_alert', event.data);
      break;

    case 'reports_update':
      io.to('moderators').to('admins').emit('reports_update', event.data);
      break;

    case 'system_alert':
      io.emit('system_alert', event.data);
      break;

    default:
      console.warn('[WS] Unknown broadcast type:', event.type);
  }
}

// ---- Stats endpoint (for monitoring) ----
httpServer.on('request', (req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      status: 'ok',
      connections: io.engine.clientsCount,
      timestamp: new Date().toISOString(),
    }));
  }
});

// ---- Start ----
setupRedis().catch(err => {
  console.warn('[Redis] Could not connect, running without Redis adapter:', err.message);
});

httpServer.listen(PORT, () => {
  console.log(`[UniLink WS] Server running on port ${PORT}`);
});
