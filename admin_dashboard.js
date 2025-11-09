// Dashboard functionality
document.addEventListener('DOMContentLoaded', () => {
    // Check authentication
    const session = checkAuth();
    
    if (session) {
        // Update welcome message
        const welcomeMessage = document.getElementById('welcomeMessage');
        welcomeMessage.textContent = `Welcome, ${session.userName}!`;
        
        // Setup components
        setupNotifications();
        setupSearch();
        setupQuickActions();
        setupLogout();
    }
});

// Setup search functionality
function setupSearch() {
    const searchInput = document.querySelector('.search-bar input');
    
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        // Implement search logic here
    });
}

// Setup quick actions
function setupQuickActions() {
    const actionButtons = document.querySelectorAll('.btn-action');
    
    actionButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const action = e.target.textContent.trim();
            handleQuickAction(action);
        });
    });
}

// Handle quick actions
function handleQuickAction(action) {
    switch (action) {
        case 'Add New User':
            // Implement user creation logic
            console.log('Adding new user...');
            break;
        case 'Create Training':
            // Implement training creation logic
            console.log('Creating new training...');
            break;
        case 'Manage Teams':
            // Implement team management logic
            console.log('Opening team management...');
            break;
        case 'View Reports':
            // Implement reports view logic
            console.log('Opening reports...');
            break;
    }
}

// Setup notifications
function setupNotifications() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationPanel = document.getElementById('notificationPanel');
    
    notificationBtn.addEventListener('click', () => {
        notificationPanel.classList.toggle('hidden');
    });
    
    // Close notification panel when clicking outside
    document.addEventListener('click', (e) => {
        if (!notificationBtn.contains(e.target) && !notificationPanel.contains(e.target)) {
            notificationPanel.classList.add('hidden');
        }
    });
    
    // Mark notifications as read
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach(notification => {
        notification.addEventListener('click', () => {
            notification.classList.remove('unread');
            updateNotificationCount();
        });
    });
}

// Update notification count
function updateNotificationCount() {
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    const countElement = document.querySelector('.notification-count');
    countElement.textContent = unreadCount;
    
    if (unreadCount === 0) {
        countElement.classList.add('hidden');
    }
}

// Setup logout functionality
function setupLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    logoutBtn.addEventListener('click', () => {
        logoutUser();
    });
}