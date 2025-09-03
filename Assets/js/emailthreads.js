/**
 * Email Threads Plugin JavaScript
 */

(function() {
    'use strict';

    // Plugin initialization
    const EmailThreads = {
        // Configuration
        config: {
            apiUrl: '/s/emailthreads/api',
            refreshInterval: 30000, // 30 seconds
            animationDuration: 300
        },

        // Initialize the plugin
        init: function() {
            this.bindEvents();
            this.loadThreads();
            this.startAutoRefresh();
        },

        // Bind event handlers
        bindEvents: function() {
            // Thread list interactions
            document.addEventListener('click', this.handleThreadClick.bind(this));
            
            // Search functionality
            const searchInput = document.getElementById('thread-search');
            if (searchInput) {
                searchInput.addEventListener('input', this.debounce(this.handleSearch.bind(this), 300));
            }

            // Filter controls
            const filterButtons = document.querySelectorAll('.thread-filter');
            filterButtons.forEach(button => {
                button.addEventListener('click', this.handleFilter.bind(this));
            });

            // Refresh button
            const refreshButton = document.getElementById('refresh-threads');
            if (refreshButton) {
                refreshButton.addEventListener('click', this.handleRefresh.bind(this));
            }

            // Modal events
            this.bindModalEvents();
        },

        // Handle thread clicks
        handleThreadClick: function(event) {
            const threadItem = event.target.closest('.thread-item');
            if (!threadItem) return;

            const threadId = threadItem.dataset.threadId;
            if (threadId) {
                if (event.target.closest('.btn-view-public')) {
                    event.preventDefault();
                    this.openPublicThread(threadId);
                } else if (event.target.closest('.btn-view-embed')) {
                    event.preventDefault();
                    this.openEmbedThread(threadId);
                } else {
                    this.viewThread(threadId);
                }
            }
        },

        // Handle search input
        handleSearch: function(event) {
            const query = event.target.value.toLowerCase();
            this.filterThreads({ search: query });
        },

        // Handle filter buttons
        handleFilter: function(event) {
            event.preventDefault();
            const button = event.target.closest('.thread-filter');
            const filter = button.dataset.filter;
            
            // Update active state
            document.querySelectorAll('.thread-filter').forEach(btn => {
                btn.classList.remove('active');
            });
            button.classList.add('active');

            this.filterThreads({ status: filter });
        },

        // Handle refresh button
        handleRefresh: function(event) {
            event.preventDefault();
            this.loadThreads(true);
        },

        // Filter threads based on criteria
        filterThreads: function(criteria) {
            const threads = document.querySelectorAll('.thread-item');
            
            threads.forEach(thread => {
                let visible = true;
                
                // Search filter
                if (criteria.search) {
                    const subject = thread.querySelector('.thread-subject').textContent.toLowerCase();
                    const lead = thread.querySelector('.thread-lead').textContent.toLowerCase();
                    visible = visible && (subject.includes(criteria.search) || lead.includes(criteria.search));
                }
                
                // Status filter
                if (criteria.status && criteria.status !== 'all') {
                    const isActive = thread.dataset.active === 'true';
                    visible = visible && ((criteria.status === 'active' && isActive) || 
                                        (criteria.status === 'inactive' && !isActive));
                }
                
                // Show/hide with animation
                if (visible) {
                    this.showElement(thread);
                } else {
                    this.hideElement(thread);
                }
            });

            this.updateEmptyState();
        },

        // Load threads from server
        loadThreads: function(force = false) {
            const container = document.getElementById('threads-container');
            if (!container) return;

            if (force) {
                this.showLoading(container);
            }

            fetch('/s/emailthreads/api/threads')
                .then(response => response.json())
                .then(data => {
                    this.renderThreads(data.threads);
                    this.hideLoading(container);
                })
                .catch(error => {
                    console.error('Failed to load threads:', error);
                    this.showError(container, 'Failed to load email threads');
                });
        },

        // Render threads in the container
        renderThreads: function(threads) {
            const container = document.getElementById('threads-list');
            if (!container) return;

            if (!threads || threads.length === 0) {
                this.showEmptyState(container);
                return;
            }

            const html = threads.map(thread => this.renderThread(thread)).join('');
            container.innerHTML = html;
        },

        // Render a single thread
        renderThread: function(thread) {
            const messageCount = thread.messageCount || 0;
            const lastMessage = new Date(thread.lastMessageDate).toLocaleDateString();
            const leadName = thread.lead ? (thread.lead.name || thread.lead.email) : 'Unknown';

            return `
                <div class="thread-item" data-thread-id="${thread.threadId}" data-active="${thread.isActive}">
                    <div class="thread-link">
                        <div class="thread-subject">${this.escapeHtml(thread.subject)}</div>
                        <div class="thread-meta">
                            <span class="thread-lead">${this.escapeHtml(leadName)}</span>
                            <div class="thread-stats">
                                <span class="message-count">${messageCount}</span>
                                <span class="thread-date">${lastMessage}</span>
                            </div>
                        </div>
                    </div>
                    <div class="thread-actions">
                        <button class="btn btn-sm btn-outline-primary btn-view-thread" title="View Details">
                            <i class="fa fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success btn-view-public" title="View Public">
                            <i class="fa fa-external-link"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info btn-view-embed" title="Embed Code">
                            <i class="fa fa-code"></i>
                        </button>
                    </div>
                </div>
            `;
        },

        // View thread details
        viewThread: function(threadId) {
            window.location.href = `/s/emailthreads/view/${threadId}`;
        },

        // Open public thread view
        openPublicThread: function(threadId) {
            const url = `/email-thread/${threadId}`;
            window.open(url, '_blank');
        },

        // Open embed thread modal
        openEmbedThread: function(threadId) {
            const embedUrl = `/email-thread/${threadId}/embed`;
            const embedCode = `<iframe src="${embedUrl}" width="100%" height="600" frameborder="0"></iframe>`;
            
            this.showModal('Embed Code', `
                <p>Copy this code to embed the email thread on your website:</p>
                <textarea class="form-control" rows="3" readonly onclick="this.select()">${embedCode}</textarea>
                <br>
                <p><small class="text-muted">The embedded thread will automatically update when new messages are added.</small></p>
            `);
        },

        // Show/hide elements with animation
        showElement: function(element) {
            element.style.display = 'block';
            element.style.opacity = '0';
            setTimeout(() => {
                element.style.transition = `opacity ${this.config.animationDuration}ms ease`;
                element.style.opacity = '1';
            }, 10);
        },

        hideElement: function(element) {
            element.style.transition = `opacity ${this.config.animationDuration}ms ease`;
            element.style.opacity = '0';
            setTimeout(() => {
                element.style.display = 'none';
            }, this.config.animationDuration);
        },

        // Loading states
        showLoading: function(container) {
            container.innerHTML = `
                <div class="emailthreads-loading">
                    <div class="spinner"></div>
                    Loading email threads...
                </div>
            `;
        },

        hideLoading: function(container) {
            const loading = container.querySelector('.emailthreads-loading');
            if (loading) {
                loading.remove();
            }
        },

        // Empty state
        showEmptyState: function(container) {
            container.innerHTML = `
                <div class="emailthreads-empty">
                    <div class="emailthreads-empty-icon">
                        <i class="fa fa-inbox"></i>
                    </div>
                    <h4 class="emailthreads-empty-title">No Email Threads</h4>
                    <p class="emailthreads-empty-text">
                        Email threads will appear here once you start sending emails to your contacts.
                    </p>
                </div>
            `;
        },

        updateEmptyState: function() {
            const visibleThreads = document.querySelectorAll('.thread-item:not([style*="display: none"])');
            const container = document.getElementById('threads-list');
            
            if (visibleThreads.length === 0) {
                this.showEmptyState(container);
            }
        },

        // Error handling
        showError: function(container, message) {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${message}
                    <button class="btn btn-link" onclick="EmailThreads.loadThreads(true)">Retry</button>
                </div>
            `;
        },

        // Modal functionality
        showModal: function(title, content) {
            let modal = document.getElementById('emailthreads-modal');
            if (!modal) {
                modal = this.createModal();
            }

            modal.querySelector('.modal-title').textContent = title;
            modal.querySelector('.modal-body').innerHTML = content;
            
            // Show modal (assuming Bootstrap or similar)
            if (typeof window.$ !== 'undefined') {
                $(modal).modal('show');
            } else {
                modal.style.display = 'block';
                modal.classList.add('show');
            }
        },

        createModal: function() {
            const modal = document.createElement('div');
            modal.id = 'emailthreads-modal';
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"></h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            return modal;
        },

        bindModalEvents: function() {
            document.addEventListener('click', (event) => {
                if (event.target.matches('[data-dismiss="modal"]')) {
                    const modal = event.target.closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                    }
                }
            });
        },

        // Auto-refresh
        startAutoRefresh: function() {
            if (this.config.refreshInterval > 0) {
                setInterval(() => {
                    this.loadThreads(false);
                }, this.config.refreshInterval);
            }
        },

        // Utility functions
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => EmailThreads.init());
    } else {
        EmailThreads.init();
    }

    // Export to global scope
    window.EmailThreads = EmailThreads;

})();
