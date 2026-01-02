// Заглушка для VKUI, так как библиотека будет подключаться отдельно
const VKUI = window.VKUI || {};
const VKIcons = window.VKIcons || {};

// Основной компонент мессенджера
class MessengerApp {
  constructor() {
    this.state = {
      activeView: 'chats',
      currentUser: null,
      token: localStorage.getItem('token'),
      chats: [],
      contacts: [],
      messages: {},
      currentChatId: null,
      newMessage: '',
      isRecording: false,
      recordingTime: 0,
      modal: null,
      isLoading: false,
      searchQuery: '',
      activeTab: 'chats',
      searchResults: []
    };

    // Проверяем авторизацию
    if (this.state.token) {
      this.loadChats();
      this.loadContacts();
    } else {
      window.location.href = 'login.html';
    }
  }

  async loadChats() {
    this.setState({ isLoading: true });
    
    try {
      const response = await fetch('/api/chats.php?action=get_chats', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${this.state.token}`
        }
      });
      
      const data = await response.json();
      
      if (data.success) {
        this.setState({ chats: data.chats });
      }
    } catch (error) {
      console.error('Error loading chats:', error);
    } finally {
      this.setState({ isLoading: false });
    }
  }

  async loadContacts() {
    try {
      const response = await fetch('/api/contacts.php?action=get_contacts', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${this.state.token}`
        }
      });
      
      const data = await response.json();
      
      if (data.success) {
        this.setState({ contacts: data.contacts });
      }
    } catch (error) {
      console.error('Error loading contacts:', error);
    }
  }

  setState(newState) {
    this.state = { ...this.state, ...newState };
    this.render();
  }

  async sendMessage() {
    if (!this.state.newMessage.trim() || !this.state.currentChatId) return;
    
    const message = {
      id: Date.now(),
      sender_id: this.state.currentUser.id,
      content: this.state.newMessage,
      type: 'text',
      created_at: new Date().toISOString()
    };
    
    // Добавляем сообщение в локальный стейт
    const newMessages = [...(this.state.messages[this.state.currentChatId] || []), message];
    this.setState({
      messages: {
        ...this.state.messages,
        [this.state.currentChatId]: newMessages
      },
      newMessage: ''
    });
    
    // Отправляем сообщение на сервер
    try {
      const response = await fetch('/api/chats.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${this.state.token}`
        },
        body: JSON.stringify({
          action: 'send_message',
          chat_id: this.state.currentChatId,
          content: message.content,
          type: 'text'
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Обновляем сообщение с данными из сервера
        const updatedMessages = this.state.messages[this.state.currentChatId].map(msg => 
          msg.id === message.id ? data.message : msg
        );
        
        this.setState({
          messages: {
            ...this.state.messages,
            [this.state.currentChatId]: updatedMessages
          }
        });
      }
    } catch (error) {
      console.error('Error sending message:', error);
    }
  }

  openChat(chatId) {
    this.setState({ currentChatId: chatId });
    this.loadMessages(chatId);
  }

  async loadMessages(chatId) {
    try {
      const response = await fetch(`/api/chats.php?action=get_messages&chat_id=${chatId}`, {
        headers: {
          'Authorization': `Bearer ${this.state.token}`
        }
      });
      
      const data = await response.json();
      
      if (data.success) {
        this.setState({
          messages: {
            ...this.state.messages,
            [chatId]: data.messages
          }
        });
      }
    } catch (error) {
      console.error('Error loading messages:', error);
    }
  }

  render() {
    // Простая реализация рендера
    const container = document.getElementById('root');
    if (!container) return;

    if (this.state.currentChatId) {
      // Рендер чата
      container.innerHTML = this.renderChat();
    } else {
      // Рендер списка чатов
      container.innerHTML = this.renderChats();
    }
  }

  renderChats() {
    return `
      <div class="messenger-container">
        <div class="header">
          <h1>Messenger</h1>
        </div>
        
        <div class="tabs">
          <button class="${this.state.activeTab === 'chats' ? 'active' : ''}" 
                  onclick="app.setState({activeTab: 'chats'})">Чаты</button>
          <button class="${this.state.activeTab === 'contacts' ? 'active' : ''}" 
                  onclick="app.setState({activeTab: 'contacts'})">Контакты</button>
        </div>
        
        <div class="content">
          ${this.state.isLoading ? 
            '<div class="loading">Загрузка...</div>' : 
            this.renderChatList()}
        </div>
      </div>
    `;
  }

  renderChatList() {
    return this.state.chats.map(chat => `
      <div class="chat-item" onclick="app.openChat(${chat.id})">
        <div class="chat-avatar">
          <img src="${chat.avatar || 'https://via.placeholder.com/48'}" alt="Avatar">
        </div>
        <div class="chat-info">
          <div class="chat-name">${chat.name}</div>
          <div class="chat-last-message">${chat.last_message}</div>
        </div>
        <div class="chat-time">${chat.time}</div>
        ${chat.unread > 0 ? `<div class="chat-unread">${chat.unread}</div>` : ''}
      </div>
    `).join('');
  }

  renderChat() {
    const messages = this.state.messages[this.state.currentChatId] || [];
    
    return `
      <div class="chat-container">
        <div class="chat-header">
          <button onclick="app.setState({currentChatId: null})">Назад</button>
          <h2>Чат</h2>
        </div>
        
        <div class="chat-messages">
          ${messages.map(message => `
            <div class="message ${message.sender_id === this.state.currentUser?.id ? 'sent' : 'received'}">
              <div class="message-content">${message.content}</div>
              <div class="message-time">${new Date(message.created_at).toLocaleTimeString()}</div>
            </div>
          `).join('')}
        </div>
        
        <div class="chat-input">
          <input 
            type="text" 
            placeholder="Сообщение..." 
            value="${this.state.newMessage}"
            oninput="app.setState({newMessage: event.target.value})"
            onkeypress="if(event.key === 'Enter') app.sendMessage()"
          />
          <button onclick="app.sendMessage()">Отправить</button>
        </div>
      </div>
    `;
  }
}

// Инициализация приложения
let app;
document.addEventListener('DOMContentLoaded', () => {
  app = new MessengerApp();
});