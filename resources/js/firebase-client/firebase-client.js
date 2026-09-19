// Firebase JavaScript Client Service
// Handles communication between Frontend and Firebase via Laravel API endpoints

class FirebaseClient {
    constructor(apiBaseUrl = '/api') {
        this.apiBaseUrl = apiBaseUrl;
        this.firebasePrefix = 'firebase';
        this.cache = new Map();
        this.listeners = new Map();
        this.isConnected = false;
    }

    /**
     * Set auth token for API requests
     */
    setAuthToken(token) {
        this.authToken = token;
    }

    /**
     * Make API request to Laravel Firebase endpoint
     */
    async apiRequest(endpoint, method = 'GET', data = null) {
        const url = `${this.apiBaseUrl}/${this.firebasePrefix}${endpoint}`;
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        };

        if (this.authToken) {
            options.headers['Authorization'] = `Bearer ${this.authToken}`;
        }

        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const json = await response.json();

            if (!response.ok) {
                throw new Error(json.message || `API Error: ${response.status}`);
            }

            return json;
        } catch (error) {
            console.error('Firebase API Request Error:', error);
            throw error;
        }
    }

    /**
     * Check Firebase connection status
     */
    async checkStatus() {
        try {
            const response = await this.apiRequest('/status');
            this.isConnected = response.connected;
            return response;
        } catch (error) {
            this.isConnected = false;
            console.error('Firebase status check failed:', error);
            throw error;
        }
    }

    /**
     * Read data from Firebase (with caching by default)
     */
    async read(path, options = {}) {
        const { cache = true, forceRefresh = false } = options;

        // Check cache first
        if (cache && !forceRefresh && this.cache.has(path)) {
            return this.cache.get(path);
        }

        const response = await this.apiRequest('/read', 'POST', {
            path,
            cache,
        });

        if (response.success && cache) {
            this.cache.set(path, response.data);
        }

        return response.data;
    }

    /**
     * Read data without caching
     */
    async readDirect(path) {
        return this.read(path, { cache: false });
    }

    /**
     * Write data to Firebase
     */
    async write(path, data) {
        const response = await this.apiRequest('/write', 'POST', {
            path,
            data,
        });

        // Invalidate cache
        this.cache.delete(path);
        this.notifyListeners(path, data);

        return response;
    }

    /**
     * Update data in Firebase
     */
    async update(path, data) {
        const response = await this.apiRequest('/update', 'POST', {
            path,
            data,
        });

        // Invalidate cache
        this.cache.delete(path);
        this.notifyListeners(path, data);

        return response;
    }

    /**
     * Delete data from Firebase
     */
    async delete(path) {
        const response = await this.apiRequest('/delete', 'POST', {
            path,
        });

        // Invalidate cache
        this.cache.delete(path);
        this.notifyListeners(path, null);

        return response;
    }

    /**
     * Push new data (creates child node with auto-generated key)
     */
    async push(path, data) {
        const response = await this.apiRequest('/push', 'POST', {
            path,
            data,
        });

        // Invalidate parent cache
        this.cache.delete(path);

        return response.key;
    }

    /**
     * Get all children at a path
     */
    async getChildren(path) {
        const response = await this.apiRequest('/children', 'POST', {
            path,
        });

        return response.children || {};
    }

    /**
     * Check if path exists
     */
    async exists(path) {
        const response = await this.apiRequest('/exists', 'POST', {
            path,
        });

        return response.exists;
    }

    /**
     * Sync order to Firebase
     */
    async syncOrder(orderId) {
        return this.apiRequest('/sync-order', 'POST', {
            order_id: orderId,
        });
    }

    /**
     * Sync message to Firebase
     */
    async syncMessage(messageId) {
        return this.apiRequest('/sync-message', 'POST', {
            message_id: messageId,
        });
    }

    /**
     * Clear cache
     */
    async clearCache(path = null) {
        const response = await this.apiRequest('/clear-cache', 'POST', {
            path,
        });

        if (path) {
            this.cache.delete(path);
        } else {
            this.cache.clear();
        }

        return response;
    }

    /**
     * Register listener for path changes
     */
    on(path, callback) {
        if (!this.listeners.has(path)) {
            this.listeners.set(path, []);
        }
        this.listeners.get(path).push(callback);

        // Return unsubscribe function
        return () => {
            const callbacks = this.listeners.get(path);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        };
    }

    /**
     * Notify listeners of data changes
     */
    notifyListeners(path, data) {
        if (this.listeners.has(path)) {
            this.listeners.get(path).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('Listener error:', error);
                }
            });
        }
    }

    /**
     * Poll for changes at a path (polling-based updates)
     */
    startPolling(path, interval = 5000, callback) {
        let lastValue = null;

        const poller = setInterval(async () => {
            try {
                const value = await this.readDirect(path);

                // Only call callback if data changed
                if (JSON.stringify(value) !== JSON.stringify(lastValue)) {
                    lastValue = value;
                    callback(value);
                }
            } catch (error) {
                console.error(`Polling error for ${path}:`, error);
            }
        }, interval);

        // Return stop function
        return () => clearInterval(poller);
    }

    /**
     * Poll multiple orders for status updates
     */
    startOrderPolling(orderIds, interval = 5000, callback) {
        const pollers = new Map();

        orderIds.forEach(orderId => {
            const path = `orders/${orderId}`;
            const stop = this.startPolling(path, interval, callback);
            pollers.set(orderId, stop);
        });

        // Return stop all function
        return () => {
            pollers.forEach(stop => stop());
            pollers.clear();
        };
    }

    /**
     * Batch read multiple paths
     */
    async readMultiple(paths) {
        const promises = paths.map(path => this.read(path));
        return Promise.all(promises);
    }

    /**
     * Batch write multiple paths
     */
    async writeMultiple(updates) {
        const promises = Object.entries(updates).map(([path, data]) => 
            this.write(path, data)
        );
        return Promise.all(promises);
    }

    /**
     * Get real-time updates for messages
     */
    async subscribeToMessages(conversationId, callback) {
        const path = `messages/${conversationId}`;
        return this.on(path, callback);
    }

    /**
     * Get real-time updates for order status
     */
    async subscribeToOrderStatus(orderId, callback) {
        const path = `orders/${orderId}`;
        
        // Poll every 3 seconds for order updates
        return this.startPolling(path, 3000, callback);
    }

    /**
     * Publish notification to user
     */
    async sendNotification(userId, notification) {
        const path = `notifications/${userId}`;
        const timestamp = new Date().toISOString();
        
        return this.push(`${path}/pending`, {
            ...notification,
            created_at: timestamp,
        });
    }

    /**
     * Mark notification as read
     */
    async markNotificationAsRead(userId, notificationId) {
        const path = `notifications/${userId}/pending/${notificationId}`;
        return this.delete(path);
    }

    /**
     * Get user notifications
     */
    async getNotifications(userId) {
        const path = `notifications/${userId}/pending`;
        try {
            return await this.getChildren(path);
        } catch (error) {
            console.error(`Error getting notifications for user ${userId}:`, error);
            return {};
        }
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FirebaseClient;
}

// Global export for inline scripts
window.FirebaseClient = FirebaseClient;
