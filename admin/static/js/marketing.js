function initMarketingPage(rootElement) {
    const adminUtils = window.adminUtils || {
        showNotification: (msg, type) => console.log(`Notification (${type}): ${msg}`),
        formatDate: (dateString) => new Date(dateString).toLocaleDateString()
    };
    const createCampaignBtn = document.getElementById('createCampaignBtn');
    const campaignModal = document.getElementById('campaignModal');
    const campaignModalTitle = document.getElementById('campaignModalTitle');
    const modalClose = campaignModal.querySelector('.modal-close');
    const cancelCampaign = document.getElementById('cancelCampaign');
    const campaignForm = document.getElementById('campaignForm');
    const campaignListSection = rootElement.querySelector('.campaign-list-section');
    const campaignReportSection = rootElement.querySelector('.campaign-report-section');
    const backToCampaignsBtn = rootElement.querySelector('.back-to-campaigns-btn');
    const reportCampaignName = document.getElementById('reportCampaignName');
    const reportDetails = document.getElementById('reportDetails');
    const reportContent = document.getElementById('reportContent');

    function openCampaignModal(title, campaign = null) {
        campaignModalTitle.textContent = title;
        if (campaign) {
            document.getElementById('campaignId').value = campaign.id;
            document.getElementById('campaignName').value = campaign.name;
            document.getElementById('campaignType').value = campaign.type;
            document.getElementById('campaignStatus').value = campaign.status;
            document.getElementById('campaignStartDate').value = campaign.start_date;
            document.getElementById('campaignEndDate').value = campaign.end_date;
            document.getElementById('campaignDescription').value = campaign.description;
        } else {
            campaignForm.reset();
            document.getElementById('campaignId').value = '';
        }
        campaignModal.style.display = 'block';
    }

    function closeCampaignModal() {
        campaignModal.style.display = 'none';
    }

    if (createCampaignBtn) {
        createCampaignBtn.addEventListener('click', () => openCampaignModal('Create New Campaign'));
    }

    if (modalClose) {
        modalClose.addEventListener('click', closeCampaignModal);
    }

    if (cancelCampaign) {
        cancelCampaign.addEventListener('click', closeCampaignModal);
    }

    window.addEventListener('click', function(event) {
        if (event.target == campaignModal) {
            closeCampaignModal();
        }
    });

    if (campaignForm) {
        campaignForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const campaignId = document.getElementById('campaignId').value;
            const campaignName = document.getElementById('campaignName').value;
            const campaignType = document.getElementById('campaignType').value;
            const campaignStatus = document.getElementById('campaignStatus').value;
            const campaignStartDate = document.getElementById('campaignStartDate').value;
            const campaignEndDate = document.getElementById('campaignEndDate').value;
            const campaignDescription = document.getElementById('campaignDescription').value;

            const campaignData = {
                id: campaignId,
                name: campaignName,
                type: campaignType,
                status: campaignStatus,
                start_date: campaignStartDate,
                end_date: campaignEndDate,
                description: campaignDescription
            };

            console.log('Saving campaign:', campaignData);
            if (campaignId) {
                updateCampaign(campaignData);
            } else {
                createCampaign(campaignData);
            }
        });
    }

    // Placeholder for edit/delete/report buttons (assuming dynamic loading)


    async function loadCampaigns() {
        try {
            const response = await fetch('../../backend/php/admin/api/campaigns.php');
            const result = await response.json();

            if (result.success) {
                displayCampaigns(result.data);
            } else {
                adminUtils.showNotification('Failed to load campaigns: ' + result.message, 'error');
            }
        } catch (error) {
            adminUtils.showNotification('Error loading campaigns: ' + error.message, 'error');
        }
    }

    function displayCampaigns(campaigns) {
        const campaignList = document.querySelector('.campaign-list');
        campaignList.innerHTML = ''; // Clear existing campaigns

        if (campaigns.length === 0) {
            campaignList.innerHTML = '<p>No campaigns found. Create a new one!</p>';
            return;
        }

        campaigns.forEach(campaign => {
            const campaignCard = document.createElement('div');
            campaignCard.className = 'campaign-card';
            campaignCard.innerHTML = `
                <h4>${campaign.name}</h4>
                <p><strong>Type:</strong> ${campaign.type}</p>
                <p><strong>Status:</strong> ${campaign.status}</p>
                <p><strong>Start Date:</strong> ${adminUtils.formatDate(campaign.start_date)}</p>
                <p><strong>End Date:</strong> ${campaign.end_date ? adminUtils.formatDate(campaign.end_date) : 'Ongoing'}</p>
                <div class="campaign-card-actions">
                    <button class="btn btn-sm btn-info edit-campaign-btn" data-id="${campaign.id}">Edit</button>
                    <button class="btn btn-sm btn-danger delete-campaign-btn" data-id="${campaign.id}">Delete</button>
                    <button class="btn btn-sm btn-success view-report-btn" data-id="${campaign.id}">View Report</button>
                </div>
            `;
            campaignList.appendChild(campaignCard);
        });

        document.querySelectorAll('.edit-campaign-btn').forEach(button => {
            button.addEventListener('click', function() {
                const campaignId = this.dataset.id;
                const campaign = campaigns.find(c => c.id == campaignId);
                if (campaign) {
                    openCampaignModal('Edit Campaign', {
                        id: campaign.id,
                        name: campaign.name,
                        type: campaign.type,
                        status: campaign.status,
                        start_date: campaign.start_date,
                        end_date: campaign.end_date,
                        description: campaign.description
                    });
                }
            });
        });

        document.querySelectorAll('.delete-campaign-btn').forEach(button => {
            button.addEventListener('click', function() {
                const campaignId = this.dataset.id;
                if (confirm('Are you sure you want to delete this campaign?')) {
                    deleteCampaign(campaignId);
                }
            });
        });

        document.querySelectorAll('.view-report-btn').forEach(button => {
            button.addEventListener('click', function() {
                const campaignId = this.dataset.id;
                rootElement.querySelector('.campaign-list').style.display = 'none';
                rootElement.querySelector('.campaign-report-section').style.display = 'block';
                loadCampaignReport(campaignId);
            });
        });
    }

    async function createCampaign(campaignData) {
        try {
            const response = await fetch('../../backend/php/admin/api/campaigns.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(campaignData)
            });
            const result = await response.json();

            if (result.success) {
                adminUtils.showNotification('Campaign created successfully!', 'success');
                closeCampaignModal();
                loadCampaigns(); // Refresh list
            } else {
                adminUtils.showNotification('Failed to create campaign: ' + result.message, 'error');
            }
        } catch (error) {
            adminUtils.showNotification('Error creating campaign: ' + error.message, 'error');
        }
    }

    async function updateCampaign(campaignData) {
        try {
            const response = await fetch('../../backend/php/admin/api/campaigns.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(campaignData)
            });
            const result = await response.json();

            if (result.success) {
                adminUtils.showNotification('Campaign updated successfully!', 'success');
                closeCampaignModal();
                loadCampaigns(); // Refresh list
            } else {
                adminUtils.showNotification('Failed to update campaign: ' + result.message, 'error');
            }
        } catch (error) {
            adminUtils.showNotification('Error updating campaign: ' + error.message, 'error');
        }
    }

    async function deleteCampaign(campaignId) {
        try {
            const response = await fetch('../../backend/php/admin/api/campaigns.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: campaignId })
            });
            const result = await response.json();

            if (result.success) {
                adminUtils.showNotification('Campaign deleted successfully!', 'success');
                loadCampaigns(); // Refresh list
            } else {
                adminUtils.showNotification('Failed to delete campaign: ' + result.message, 'error');
            }
        } catch (error) {
            adminUtils.showNotification('Error deleting campaign: ' + error.message, 'error');
        }
    }

    // Initial load of campaigns
    loadCampaigns();

    if (backToCampaignsBtn) {
        backToCampaignsBtn.addEventListener('click', () => {
            campaignReportSection.style.display = 'none';
            campaignListSection.style.display = 'block';
            loadCampaigns(); // Refresh campaign list when returning
        });
    }

    async function loadCampaignReport(campaignId) {
        reportCampaignName.textContent = '';
        reportDetails.innerHTML = '';
        reportContent.innerHTML = '<p>Loading report data...</p>';

        try {
            // Fetch campaign details
            const campaignResponse = await fetch(`../../backend/php/admin/api/campaigns.php?id=${campaignId}`);
            const campaignResult = await campaignResponse.json();

            if (campaignResult.success && campaignResult.data && campaignResult.data.length > 0) {
                const campaign = campaignResult.data[0];
                reportCampaignName.textContent = `Report for: ${campaign.name}`;
                reportDetails.innerHTML = `
                    <p><strong>ID:</strong> ${campaign.id}</p>
                    <p><strong>Type:</strong> ${campaign.type}</p>
                    <p><strong>Status:</strong> ${campaign.status}</p>
                    <p><strong>Start Date:</strong> ${adminUtils.formatDate(campaign.start_date)}</p>
                    <p><strong>End Date:</strong> ${campaign.end_date ? adminUtils.formatDate(campaign.end_date) : 'Ongoing'}</p>
                    <p><strong>Description:</strong> ${campaign.description}</p>
                `;

                // Fetch actual report data (e.g., analytics)
                const reportDataResponse = await fetch(`../../backend/php/admin/api/campaign_report_api.php?campaign_id=${campaignId}`);
                const reportDataResult = await reportDataResponse.json();

                if (reportDataResult.success) {
                    reportContent.innerHTML = `
                        <h4>Analytics Data:</h4>
                        <div class="analytics-summary">
                            <p><strong>Total Clicks:</strong> ${reportDataResult.data.metrics.total_clicks}</p>
                            <p><strong>Total Views:</strong> ${reportDataResult.data.metrics.total_views}</p>
                            <p><strong>Conversion Rate:</strong> ${reportDataResult.data.metrics.conversion_rate}</p>
                            <p><strong>Revenue Generated:</strong> ₹${reportDataResult.data.metrics.revenue_generated}</p>
                        </div>
                        <canvas id="campaignPerformanceChart"></canvas>
                    `;

                    const ctx = document.getElementById('campaignPerformanceChart').getContext('2d');
                    const performanceData = reportDataResult.data.performance_over_time;
                    const labels = performanceData.map(item => item.date);
                    const clicksData = performanceData.map(item => item.clicks);
                    const viewsData = performanceData.map(item => item.views);

                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Clicks',
                                    data: clicksData,
                                    borderColor: 'rgb(75, 192, 192)',
                                    tension: 0.1
                                },
                                {
                                    label: 'Views',
                                    data: viewsData,
                                    borderColor: 'rgb(255, 99, 132)',
                                    tension: 0.1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                } else {
                    reportContent.innerHTML = `<p>Failed to load report data: ${reportDataResult.message}</p>`;
                }
            } else {
                reportContent.innerHTML = `<p>Campaign details not found: ${campaignResult.message}</p>`;
            }
        } catch (error) {
            reportContent.innerHTML = `<p>Error loading campaign report: ${error.message}</p>`;
        }
    }
}

// Expose initMarketingPage to the global scope
window.initMarketingPage = initMarketingPage;

// Ensure initMarketingPage runs when the DOM is fully loaded