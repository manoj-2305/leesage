document.addEventListener('DOMContentLoaded', function() {
  // Check if user is logged in with better error handling
  async function checkProfileLoginStatus() {
    try {
      const response = await fetch('/leesage/backend/php/public/auth/check_session.php', {
        method: 'GET',
        credentials: 'include'
      });
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      
      const data = await response.json();
      
      if (data.success && data.data && data.data.isLoggedIn) {
        // User is logged in, fetch profile data from API
        fetchUserProfile();
      } else {
        // User is not logged in, redirect to login page
        window.location.href = 'login.html';
      }
    } catch (error) {
      console.error('Error checking login status for profile:', error);
      window.location.href = 'login.html';
    }
  }

  // Fetch user profile data from the API
  async function fetchUserProfile() {
    try {
      const response = await fetch('/leesage/backend/php/public/api/profile.php', {
        method: 'GET',
        credentials: 'include'
      });
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      
      const data = await response.json();
      
      if (data.success) {
        // Display user profile information
        displayProfileInfo(data.data);
      } else {
        window.location.href = 'login.html';
      }
    } catch (error) {
      console.error('Error fetching user profile:', error);
      window.location.href = 'login.html';
    }
  }

  // Display user profile information
  function displayProfileInfo(user) {
    const profileContent = document.getElementById('profile-content');
    
    // Format dates
    const createdDate = user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A';
    const updatedDate = user.updated_at ? new Date(user.updated_at).toLocaleDateString() : 'N/A';
    
    // Create profile image or fallback
    const profileImage = user.profile_image 
      ? `<img src="${user.profile_image}" alt="Profile Image" class="profile-image">` 
      : `<span class="avatar">${user.first_name ? user.first_name.charAt(0) : 'U'}${user.last_name ? user.last_name.charAt(0) : ''}</span>`;
    
    profileContent.innerHTML = `
      <div class="profile-avatar-large">
        ${profileImage}
      </div>
      <div class="profile-info">
        <h2>${user.first_name} ${user.last_name}</h2>
        <p class="user-email">${user.email}</p>
        <div class="profile-details">
          <p><strong>Member since:</strong> ${createdDate}</p>
          <p><strong>Last updated:</strong> ${updatedDate}</p>
        </div>
        <div class="profile-actions">
          <button id="edit-profile-button" class="btn">Edit Profile</button>
          <button id="view-orders-button" class="btn">View Orders</button>
          <button id="view-wishlist-button" class="btn">View Wishlist</button>
        </div>
      </div>
    `;
    
    // Add event listeners
    document.getElementById('edit-profile-button').addEventListener('click', function() {
      alert('Edit profile functionality will be implemented soon!');
    });
    
    document.getElementById('view-orders-button').addEventListener('click', function() {
      window.location.href = 'orders.html';
    });
    
    document.getElementById('view-wishlist-button').addEventListener('click', function() {
      window.location.href = 'wishlist.html';
    });
  }

  // Add logout functionality
  document.getElementById('logout-button').addEventListener('click', async function() {
    try {
      const response = await fetch('/leesage/backend/php/public/auth/logout.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'include'
      });
      
      const data = await response.json();

      if (data.success) {
        // Clear local storage
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userFirstName');
        localStorage.removeItem('userEmail');
        
        alert('Logged out successfully!');
        window.location.href = 'home.html';
      } else {
        alert('Logout failed: ' + data.message);
      }
    } catch (error) {
      console.error('Error during logout:', error);
      alert('An error occurred during logout.');
    }
  });

  // Initialize the page
  checkProfileLoginStatus();
});
