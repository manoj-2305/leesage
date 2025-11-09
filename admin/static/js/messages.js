const messagesAPI = {
  getMessages: async (page = 1, search = '', status = '') => {
    try {
      const params = new URLSearchParams({ page, search, status });
      const res = await fetch('../../backend/php/admin/api/get_messages.php?' + params, {
        credentials: 'include'
      });
      const data = await res.json();
      console.log('getMessages response:', data);
      return data;
    } catch (err) {
      console.error('Error fetching messages:', err);
      return { success: false, message: 'Failed to fetch messages' };
    }
  },

  getMessageDetails: async (messageId) => {
    try {
      const res = await fetch('../../backend/php/admin/api/get_message_details.php?message_id=' + messageId, {
        credentials: 'include'
      });
      return await res.json();
    } catch (err) {
      console.error('Error fetching message details:', err);
      return { success: false, message: 'Failed to fetch message details' };
    }
  },

  updateMessageStatus: async (messageId, status) => {
    try {
      const form = new FormData();
      form.append('message_id', messageId);
      form.append('status', status); // 'open' or 'closed'

      const res = await fetch('../../backend/php/admin/actions/update_message_status.php', {
        method: 'POST',
        body: form,
        credentials: 'include'
      });
      return await res.json();
    } catch (err) {
      console.error('Error updating message status:', err);
      return { success: false, message: 'Failed to update message status' };
    }
  },

  deleteMessage: async (messageId) => {
    try {
      const form = new FormData();
      form.append('message_id', messageId);

      const res = await fetch('../../backend/php/admin/actions/delete_message.php', {
        method: 'POST',
        body: form,
        credentials: 'include'
      });
      return await res.json();
    } catch (err) {
      console.error('Error deleting message:', err);
      return { success: false, message: 'Failed to delete message' };
    }
  }
};

// --- Rendering + interactions ---
async function loadMessages(page = 1, search = '', status = '') {
  try {
    const response = await messagesAPI.getMessages(page, search, status);
    if (!response.success) throw new Error(response.message || 'Failed to load messages');

    renderMessagesTable(response.data.messages);
    renderPagination(response.data.pagination);
  } catch (err) {
    console.error('Error loading messages:', err);
    adminUtils.showNotification('Error loading messages', 'error');
  }
}

function renderMessagesTable(messages) {
  console.log('Messages received by renderMessagesTable:', messages);
  const tbody = document.querySelector('.messages-table tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!messages || messages.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px;">No messages found</td></tr>`;
    return;
  }

  messages.forEach(m => {
    // Map DB to UI: is_read/replied -> New/Open/Closed
    let statusText = 'New';
    let statusClass = 'status-new';
    if (m.replied) { statusText = 'Closed'; statusClass = 'status-closed'; }
    else if (m.is_read) { statusText = 'Open'; statusClass = 'status-open'; }

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${m.id}</td>
      <td>${m.name}</td>
      <td>${m.email}</td>
      <td>${m.subject}</td>
      <td>${adminUtils.formatDate(m.created_at)}</td>
      <td><span class="status-badge ${statusClass}">${statusText}</span></td>
      <td>
        <button class="btn btn-sm btn-primary view-btn" data-id="${m.id}">View</button>
        <button class="btn btn-sm btn-danger delete-btn" data-id="${m.id}">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  // Attach event listeners to buttons
  attachButtonListeners();
}

function attachButtonListeners() {
  // View buttons
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = e.target.getAttribute('data-id');
      viewMessage(id);
    });
  });

  // Delete buttons
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = e.target.getAttribute('data-id');
      deleteMessage(id);
    });
  });
}

function renderPagination(pagination) {
  const paginationContainer = document.getElementById('messagesPagination');
  if (!paginationContainer) {
    console.warn('Messages pagination container not found');
    return;
  }

  paginationContainer.innerHTML = '';

  if (!pagination || pagination.total_pages <= 1) {
    return;
  }

  let paginationHtml = `<ul class="pagination justify-content-center">`;

  // Previous button
  paginationHtml += `<li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                          <a class="page-link" href="#" onclick="loadMessages(${pagination.current_page - 1}, '${pagination.search || ''}', '${pagination.status || ''}')">Previous</a>
                      </li>`;

  // Page numbers
  for (let i = 1; i <= pagination.total_pages; i++) {
    paginationHtml += `<li class="page-item ${pagination.current_page === i ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="loadMessages(${i}, '${pagination.search || ''}', '${pagination.status || ''}')">${i}</a>
                        </li>`;
  }

  // Next button
  paginationHtml += `<li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                          <a class="page-link" href="#" onclick="loadMessages(${pagination.current_page + 1}, '${pagination.search || ''}', '${pagination.status || ''}')">Next</a>
                      </li>`;

  paginationHtml += `</ul>`;
  paginationContainer.innerHTML = paginationHtml;
}

function filterMessages() {
  console.log('Filter button clicked');
  const status = document.getElementById('messageStatusFilter')?.value || '';
  const searchTerm = document.getElementById('messageSearch')?.value || '';
  console.log('Filtering with status:', status, 'search:', searchTerm);
  loadMessages(1, searchTerm, status);
}
window.filterMessages = filterMessages;

// Ensure filter button works in both initialization contexts
function setupFilterButton() {
  const filterButton = document.getElementById('filterMessagesBtn');
  if (filterButton) {
    filterButton.removeEventListener('click', filterMessages);
    filterButton.addEventListener('click', filterMessages);
    console.log('Filter button listener attached');
  }
}

async function viewMessage(messageId) {
  try {
    const response = await messagesAPI.getMessageDetails(messageId);
    if (!response.success) throw new Error(response.message || 'Failed to load message details');

    const m = response.data;
    document.getElementById('modalMessageId').textContent = m.id;
    document.getElementById('modalMessageSender').textContent = m.name;
    document.getElementById('modalMessageEmail').textContent = m.email;
    document.getElementById('modalMessageSubject').textContent = m.subject;
    document.getElementById('modalMessageDate').textContent = adminUtils.formatDate(m.created_at);

    let statusText = 'New';
    if (m.replied) statusText = 'Closed';
    else if (m.is_read) statusText = 'Open';
    document.getElementById('modalMessageStatus').textContent = statusText;

    document.getElementById('modalMessageContent').textContent = m.message;

    const openBtn = document.getElementById('modalMarkOpenBtn');
    const closedBtn = document.getElementById('modalMarkClosedBtn');
    if (openBtn) openBtn.style.display = m.replied ? 'inline-block' : 'none';
    if (closedBtn) closedBtn.style.display = !m.replied ? 'inline-block' : 'none';

    document.getElementById('messageDetailsModal').style.display = 'block';
  } catch (err) {
    console.error('Error viewing message:', err);
    adminUtils.showNotification('Error loading message details', 'error');
  }
}
window.viewMessage = viewMessage;

function closeMessageModal() {
  const modal = document.getElementById('messageDetailsModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

// Function to initialize message page specific functionalities
function initMessagesPage() {
  setupFilterButton();
  loadMessages();

  // Attach event listeners for modal buttons
  const modalCloseBtn = document.querySelector('#messageDetailsModal .modal-close');
  if (modalCloseBtn) {
    modalCloseBtn.addEventListener('click', closeMessageModal);
  }

  const modalMarkOpenBtn = document.getElementById('modalMarkOpenBtn');
  if (modalMarkOpenBtn) {
    modalMarkOpenBtn.addEventListener('click', async () => {
      const messageId = document.getElementById('modalMessageId').textContent;
      const response = await messagesAPI.updateMessageStatus(messageId, 'open');
      if (response.success) {
        adminUtils.showNotification('Message marked as open', 'success');
        closeMessageModal();
        loadMessages(); // Reload messages to reflect status change
      } else {
        adminUtils.showNotification(response.message || 'Failed to mark message as open', 'error');
      }
    });
  }

  const modalMarkClosedBtn = document.getElementById('modalMarkClosedBtn');
  if (modalMarkClosedBtn) {
    modalMarkClosedBtn.addEventListener('click', async () => {
      const messageId = document.getElementById('modalMessageId').textContent;
      const response = await messagesAPI.updateMessageStatus(messageId, 'closed');
      if (response.success) {
        adminUtils.showNotification('Message marked as closed', 'success');
        closeMessageModal();
        loadMessages(); // Reload messages to reflect status change
      } else {
        adminUtils.showNotification(response.message || 'Failed to mark message as closed', 'error');
      }
    });
  }
}

// Export the initMessagesPage function to be called from dashboard.js
window.initMessagesPage = initMessagesPage;

function closeMessageModal() {
  const modal = document.getElementById('messageDetailsModal');
  if (modal) modal.style.display = 'none';
}
window.closeMessageModal = closeMessageModal;

async function updateMessageStatus(messageId, status) {
  try {
    const response = await messagesAPI.updateMessageStatus(messageId, status); // 'open'|'closed'
    if (!response.success) throw new Error(response.message || 'Failed to update message status');

    adminUtils.showNotification('Message status updated successfully', 'success');
    closeMessageModal();

    const statusFilter = document.getElementById('messageStatusFilter')?.value || '';
    const searchTerm = document.getElementById('messageSearch')?.value || '';
    loadMessages(1, searchTerm, statusFilter);
  } catch (err) {
    console.error('Error updating message status:', err);
    adminUtils.showNotification('Error updating message status', 'error');
  }
}
window.updateMessageStatus = updateMessageStatus;

async function deleteMessage(messageId) {
  if (!confirm('Are you sure you want to delete this message?')) return;

  try {
    const response = await messagesAPI.deleteMessage(messageId);
    if (!response.success) throw new Error(response.message || 'Failed to delete message');

    adminUtils.showNotification('Message deleted successfully', 'success');
    const statusFilter = document.getElementById('messageStatusFilter')?.value || '';
    const searchTerm = document.getElementById('messageSearch')?.value || '';
    loadMessages(1, searchTerm, statusFilter);
  } catch (err) {
    console.error('Error deleting message:', err);
    adminUtils.showNotification('Error deleting message', 'error');
  }
}
window.deleteMessage = deleteMessage;

// --- Safe initializer (call this AFTER messages.html is injected) ---
function initMessages() {
  // Guard: only if the page exists
  if (!document.querySelector('.messages-content')) return;

  const modal = document.getElementById('messageDetailsModal');
  const modalClose = modal?.querySelector('.modal-close');
  if (modalClose) modalClose.addEventListener('click', closeMessageModal);

  window.addEventListener('click', (evt) => {
    if (evt.target === modal) closeMessageModal();
  });

  // Modal buttons
  const openBtn = document.getElementById('modalMarkOpenBtn');
  const closedBtn = document.getElementById('modalMarkClosedBtn');
  if (openBtn) openBtn.onclick = () => {
    const id = document.getElementById('modalMessageId').textContent;
    updateMessageStatus(id, 'open');
  };
  if (closedBtn) closedBtn.onclick = () => {
    const id = document.getElementById('modalMessageId').textContent;
    updateMessageStatus(id, 'closed');
  };

  // Initial load
  loadMessages();

  // Event listener for filter button
  const filterBtn = document.getElementById('filterMessagesBtn');
  if (filterBtn) {
    filterBtn.addEventListener('click', filterMessages);
  }
}
window.initMessages = initMessages;

// Optional: if messages page is the FIRST page (direct load), try initializing
document.addEventListener('DOMContentLoaded', () => {
  if (document.querySelector('.messages-content')) initMessages();
});
