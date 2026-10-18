<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CollabSpace AI - Real-Time Chat Live Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col font-sans">
    <div class="max-w-4xl w-full mx-auto p-6 flex-1 flex flex-col">
        <!-- Header -->
        <header class="border-b border-slate-800 pb-4 mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
                    CollabSpace AI - Real-Time Chat Monitor
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    Powered by Laravel Reverb WebSockets &middot; Channel: <code class="text-blue-400">private-projects.{projectId}</code>
                </p>
            </div>
            <div id="statusBadge" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                Disconnected
            </div>
        </header>

        <!-- Connection Card -->
        <div class="bg-slate-800/60 rounded-xl p-4 border border-slate-700/60 mb-6 backdrop-blur">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-slate-400 mb-1">Project ID</label>
                    <input id="projectId" type="number" value="1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 font-mono">
                </div>
                <div class="md:col-span-7">
                    <label class="block text-xs font-medium text-slate-400 mb-1">Bearer Token (from login)</label>
                    <input id="bearerToken" type="text" placeholder="1|laravel_sanctum_token_here..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 font-mono">
                </div>
                <div class="md:col-span-2 flex items-end">
                    <button id="connectBtn" onclick="toggleConnection()" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-medium py-2 px-4 rounded-lg text-sm transition">
                        Connect
                    </button>
                </div>
            </div>
        </div>

        <!-- Chat Container -->
        <div class="flex-1 bg-slate-800/40 border border-slate-800 rounded-xl flex flex-col overflow-hidden mb-6">
            <div class="px-4 py-3 bg-slate-800/80 border-b border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                <span>Incoming Live Messages</span>
                <span id="msgCount" class="font-mono">0 messages</span>
            </div>
            <div id="messagesList" class="flex-1 p-4 overflow-y-auto space-y-3 min-h-[350px]">
                <div id="emptyState" class="h-full flex flex-col items-center justify-center text-slate-500 py-16">
                    <svg class="w-12 h-12 mb-3 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="text-sm">Connect your token above to start listening to incoming messages.</p>
                </div>
            </div>
        </div>

        <!-- Direct Send Tester -->
        <div class="bg-slate-800/60 rounded-xl p-4 border border-slate-700/60">
            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Send Message (API Trigger)</h3>
            <div class="flex gap-2">
                <input id="quickMessage" type="text" placeholder="Type a message to test send via API..." class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                <button onclick="sendQuickMessage()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2 px-5 rounded-lg text-sm transition">
                    Send via API
                </button>
            </div>
        </div>
    </div>

    <script>
        let pusherInstance = null;
        let activeChannel = null;
        let isConnected = false;
        let count = 0;

        function updateStatus(connected) {
            isConnected = connected;
            const badge = document.getElementById('statusBadge');
            const btn = document.getElementById('connectBtn');

            if (connected) {
                badge.className = "flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20";
                badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Connected';
                btn.textContent = "Disconnect";
                btn.className = "w-full bg-rose-600 hover:bg-rose-500 text-white font-medium py-2 px-4 rounded-lg text-sm transition";
            } else {
                badge.className = "flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20";
                badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-rose-500"></span> Disconnected';
                btn.textContent = "Connect";
                btn.className = "w-full bg-blue-600 hover:bg-blue-500 text-white font-medium py-2 px-4 rounded-lg text-sm transition";
            }
        }

        function toggleConnection() {
            if (isConnected) {
                if (activeChannel) {
                    pusherInstance.unsubscribe(activeChannel.name);
                }
                if (pusherInstance) {
                    pusherInstance.disconnect();
                }
                updateStatus(false);
                return;
            }

            const token = document.getElementById('bearerToken').value.trim();
            const projectId = document.getElementById('projectId').value.trim() || 1;

            if (!token) {
                alert("Please paste your Sanctum Bearer Token from login!");
                return;
            }

            // Configure Pusher client pointing to local Laravel Reverb server
            pusherInstance = new Pusher('collabspace-key', {
                wsHost: '127.0.0.1',
                wsPort: 8080,
                wssPort: 8080,
                forceTLS: false,
                enabledTransports: ['ws', 'wss'],
                authEndpoint: '/api/v1/broadcasting/auth',
                auth: {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                }
            });

            const channelName = 'private-projects.' + projectId;
            activeChannel = pusherInstance.subscribe(channelName);

            activeChannel.bind('pusher:subscription_succeeded', () => {
                updateStatus(true);
                const empty = document.getElementById('emptyState');
                if (empty) empty.innerHTML = '<p class="text-sm text-emerald-400 font-medium">Subscribed to channel: ' + channelName + '. Waiting for messages...</p>';
            });

            activeChannel.bind('pusher:subscription_error', (status) => {
                alert('Authentication failed for channel. Please verify your token and project access.');
                updateStatus(false);
            });

            // Listen to our custom event broadcastAs name: 'message.sent'
            activeChannel.bind('message.sent', (data) => {
                appendMessage(data);
            });
        }

        function appendMessage(data) {
            const list = document.getElementById('messagesList');
            const empty = document.getElementById('emptyState');
            if (empty) empty.remove();

            count++;
            document.getElementById('msgCount').textContent = count + (count === 1 ? ' message' : ' messages');

            const item = document.createElement('div');
            item.className = "bg-slate-800/80 border border-slate-700/60 rounded-lg p-3.5 flex items-start gap-3 transition transform hover:translate-x-1";

            const senderInitial = (data.sender && data.sender.name) ? data.sender.name.charAt(0).toUpperCase() : 'U';
            const senderName = (data.sender && data.sender.name) ? data.sender.name : 'Unknown User';
            const time = data.created_at ? new Date(data.created_at).toLocaleTimeString() : new Date().toLocaleTimeString();

            item.innerHTML = `
                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center font-bold text-xs text-white shrink-0 shadow">
                    ${senderInitial}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-semibold text-slate-200">${senderName}</span>
                        <span class="text-xs text-slate-500">${time}</span>
                    </div>
                    <p class="text-sm text-slate-300 break-words">${data.message || ''}</p>
                </div>
            `;

            list.appendChild(item);
            list.scrollTop = list.scrollHeight;
        }

        async function sendQuickMessage() {
            const input = document.getElementById('quickMessage');
            const message = input.value.trim();
            const token = document.getElementById('bearerToken').value.trim();
            const projectId = document.getElementById('projectId').value.trim() || 1;

            if (!message) return;
            if (!token) {
                alert("Please enter your Bearer token first.");
                return;
            }

            try {
                const res = await fetch(`/api/v1/projects/${projectId}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message: message })
                });

                if (res.ok) {
                    input.value = '';
                } else {
                    const err = await res.json();
                    alert("Error sending message: " + (err.message || res.statusText));
                }
            } catch (e) {
                alert("Request failed: " + e.message);
            }
        }
    </script>
</body>
</html>
