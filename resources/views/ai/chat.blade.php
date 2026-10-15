<x-app-layout>
    @php
        $chatUser = auth()->user();
        $chatInit = [
            'conversationId' => $conversation?->id,
            'conversations' => $conversations ?? [],
            'messages' => $messages ?? [],
            'user' => [
                'name' => $chatUser?->name ?? '',
                'initial' => strtoupper(mb_substr($chatUser?->name ?? '?', 0, 1)),
                'photo' => $chatUser?->avatar ? asset('storage/' . $chatUser->avatar) : null,
            ],
        ];
    @endphp
    <script>window.chatInit = @json($chatInit);</script>

    <div class="ai-page" x-data="officeAssistant()">
        <aside class="ai-history" :class="historyOpen ? 'is-open' : ''" aria-label="{{ __('Riwayat percakapan') }}">
            <div class="ai-history-head">
                <div class="ai-history-brand">
                    <span class="ai-history-mark"><x-icon name="message" class="h-4 w-4" /></span>
                    <span>Office Assistant</span>
                </div>
                <button type="button" class="ai-icon-button ai-history-close" @click="historyOpen = false" aria-label="{{ __('Tutup riwayat percakapan') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>

            <button type="button" class="ai-new-chat" @click="newChat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                </svg>
                <span>New Chat</span>
            </button>

            <div class="ai-history-label">{{ __('Riwayat chat') }}</div>
            <div class="ai-history-list">
                <div x-show="conversations.length === 0" class="ai-history-empty" x-cloak>
                    {{ __('Belum ada percakapan.') }}
                </div>

                <template x-for="chat in conversations" :key="chat.id">
                    <div class="ai-history-item" :class="conversationId === chat.id ? 'is-active' : ''">
                        <button type="button" class="ai-history-link" x-show="editingId !== chat.id" @click="openConversation(chat.id)" :aria-current="conversationId === chat.id ? 'page' : 'false'">
                            <span class="ai-history-title" x-text="chat.title"></span>
                            <span class="ai-history-date" x-text="formatDate(chat.updated_at)"></span>
                        </button>
                        <button type="button" class="ai-history-more" x-show="editingId !== chat.id" @click.stop="menuId = menuId === chat.id ? null : chat.id" :aria-expanded="menuId === chat.id" aria-label="{{ __('Aksi percakapan') }}">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" />
                            </svg>
                        </button>
                        <div x-show="menuId === chat.id && editingId !== chat.id" x-cloak @click.outside="menuId = null" class="ai-history-menu">
                            <button type="button" @click="beginRename(chat)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.121 2.121 0 013 3L7.5 18.849 3 20l1.151-4.5L16.862 3.487z" />
                                </svg>
                                Rename
                            </button>
                            <button type="button" class="is-danger" @click="deleteConversation(chat)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M9 7V4h6v3m-9 0 1 13h10l1-13" />
                                </svg>
                                {{ __('Hapus') }}
                            </button>
                        </div>
                        <div x-show="editingId === chat.id" x-cloak class="ai-history-rename">
                            <input type="text" x-model="renameDraft" maxlength="100" aria-label="{{ __('Nama percakapan baru') }}" @keydown.enter.prevent="saveRename(chat)" @keydown.escape="cancelRename">
                            <button type="button" @click="saveRename(chat)" aria-label="{{ __('Simpan nama percakapan') }}">✓</button>
                            <button type="button" @click="cancelRename" aria-label="{{ __('Batalkan rename') }}">&times;</button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="ai-history-foot">
                <span class="ai-status-dot" aria-hidden="true"></span>
                <span>{{ __('Data realtime dari sistem') }}</span>
            </div>
        </aside>

        <div class="ai-history-backdrop" x-show="historyOpen" x-cloak @click="historyOpen = false"></div>

        <section class="ai-workspace">
            <header class="ai-chat-header">
                <div class="ai-chat-heading">
                    <button type="button" class="ai-icon-button ai-mobile-history" @click="historyOpen = true" aria-label="{{ __('Buka riwayat percakapan') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="ai-chat-icon"><x-icon name="message" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <h1>Office Assistant</h1>
                        <p><span class="ai-status-dot" aria-hidden="true"></span> {{ __('Asisten AI internal') }}</p>
                    </div>
                </div>
                <button type="button" class="ai-header-new" @click="newChat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                    </svg>
                    <span>New Chat</span>
                </button>
            </header>

            <div class="ai-conversation" x-ref="messages" @scroll="onScroll" aria-live="polite">
                <div x-show="messages.length === 0 && !loadingConversation" class="ai-empty" x-cloak>
                    <div class="ai-empty-mark"><x-icon name="message" class="h-6 w-6" /></div>
                    <h2>{{ __('Mulai percakapan') }}</h2>
                    <p>{{ __('Tanyakan tentang project, tugas, dokumen, atau data perusahaan.') }}</p>
                    <div class="ai-suggestions">
                        <button type="button" @click="draft = '{{ __('Apa tugas saya yang paling dekat?') }}'; focusInput()">{{ __('Tugas saya') }}</button>
                        <button type="button" @click="draft = '{{ __('Tampilkan project terbaru saya') }}'; focusInput()">{{ __('Project terbaru') }}</button>
                        <button type="button" @click="draft = '{{ __('Bagaimana ringkasan penjualan terbaru?') }}'; focusInput()">{{ __('Ringkasan penjualan') }}</button>
                    </div>
                </div>

                <div x-show="loadingConversation" class="ai-loading-history" x-cloak role="status">
                    <span></span><span></span><span></span>
                    {{ __('Memuat percakapan…') }}
                </div>

                <template x-for="msg in messages" :key="msg.id">
                    <article class="ai-message" :class="msg.role === 'user' ? 'is-user' : 'is-assistant'">
                        <template x-if="msg.role === 'assistant'">
                            <div class="ai-assistant-row">
                                <span class="ai-message-avatar"><x-icon name="message" class="h-5 w-5" /></span>
                                <div class="ai-message-body">
                                    <div class="ai-message-author">Office Assistant</div>
                                    <div class="md" x-html="renderMarkdown(msg.content)"></div>
                                    <div class="ai-sources" x-show="msg.sources && msg.sources.length" x-cloak>
                                        <template x-for="src in msg.sources" :key="src.name">
                                            <span class="ai-source-chip">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h8l4 4v14H6zM14 3v5h5" />
                                                </svg>
                                                <span x-text="src.name"></span>
                                                <span x-show="src.category" x-text="' · '+src.category"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="msg.role === 'user'">
                            <div class="ai-user-row">
                                <div class="ai-user-text">
                                    <div class="ai-user-msg" x-show="msg.content" x-text="msg.content"></div>
                                    <div class="ai-message-files" x-show="msg.attachments && msg.attachments.length" x-cloak>
                                        <template x-for="file in msg.attachments" :key="file.name">
                                            <span class="ai-file-chip">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6" />
                                                </svg>
                                                <span x-text="file.name"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <span class="ai-user-avatar" aria-hidden="true">
                                    <img x-show="userPhoto" :src="userPhoto" :alt="userName" loading="lazy" decoding="async">
                                    <span x-show="!userPhoto" x-text="userInitial"></span>
                                </span>
                            </div>
                        </template>
                    </article>
                </template>

                <div x-show="busy" class="ai-assistant-row ai-thinking" x-cloak role="status" aria-label="{{ __('AI sedang berpikir') }}">
                    <span class="ai-message-avatar"><x-icon name="message" class="h-5 w-5" /></span>
                    <div class="ai-thinking-body"><span></span><span></span><span></span></div>
                </div>
            </div>

            <button type="button" class="ai-jump" x-show="!atBottom" @click="scrollToBottom(true)" x-cloak aria-label="{{ __('Kembali ke pesan terbaru') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" />
                </svg>
            </button>

            <div class="ai-composer-area">
                <div x-show="error" class="ai-error" x-cloak role="alert">
                    <span x-text="error"></span>
                    <button type="button" @click="error = ''" aria-label="{{ __('Tutup pesan kesalahan') }}">&times;</button>
                </div>

                <div x-show="attachments.length" class="ai-attachment-preview" x-cloak aria-label="{{ __('Lampiran terpilih') }}">
                    <template x-for="(attachment, index) in attachments" :key="attachment.id">
                        <div class="ai-attachment-card">
                            <template x-if="attachment.isImage">
                                <img :src="attachment.previewUrl" :alt="attachment.name">
                            </template>
                            <template x-if="!attachment.isImage">
                                <span class="ai-attachment-file-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6" />
                                    </svg>
                                </span>
                            </template>
                            <span class="ai-attachment-meta">
                                <strong x-text="attachment.name"></strong>
                                <small x-text="formatBytes(attachment.size)"></small>
                            </span>
                            <button type="button" @click="removeAttachment(index)" aria-label="{{ __('Hapus lampiran') }}">
                                &times;
                            </button>
                        </div>
                    </template>
                </div>

                <form class="ai-composer" @submit.prevent="send">
                    <div class="ai-plus-wrap">
                        <button type="button" class="ai-composer-button" @click="plusOpen = !plusOpen" :aria-expanded="plusOpen" aria-label="{{ __('Tambah lampiran atau input suara') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" d="M12 5v14m-7-7h14" />
                            </svg>
                        </button>
                        <div x-show="plusOpen" x-cloak @click.outside="plusOpen = false" class="ai-plus-menu">
                            <button type="button" @click="$refs.imageInput.click(); plusOpen = false">
                                <span class="ai-menu-icon is-image">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9" r="1.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 16l4-4 3 3 3-3 8 7" />
                                    </svg>
                                </span>
                                <span>{{ __('Tambahkan gambar') }}</span>
                            </button>
                            <button type="button" @click="$refs.fileInput.click(); plusOpen = false">
                                <span class="ai-menu-icon is-file">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6" />
                                    </svg>
                                </span>
                                <span>{{ __('Tambahkan file') }}</span>
                            </button>
                            <button type="button" @click="toggleSpeech(); plusOpen = false" :class="listening ? 'is-listening' : ''">
                                <span class="ai-menu-icon is-mic">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="9" y="3" width="6" height="11" rx="3" /><path stroke-linecap="round" d="M5 11a7 7 0 0014 0M12 18v3m-3 0h6" />
                                    </svg>
                                </span>
                                <span x-text="listening ? '{{ __('Berhenti merekam') }}' : '{{ __('Input suara') }}'"></span>
                            </button>
                        </div>
                    </div>

                    <input x-ref="imageInput" class="sr-only" type="file" accept="image/jpeg,image/png,image/gif,image/webp" @change="addFiles($event)">
                    <input x-ref="fileInput" class="sr-only" type="file" accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx" @change="addFiles($event)">

                    <textarea x-model="draft" x-ref="input" rows="1" placeholder="{{ __('Tulis pesan untuk Office Assistant…') }}"
                              aria-label="{{ __('Tulis pesan untuk Office Assistant') }}"
                              @input="resize"
                              @keydown.enter="onEnter($event)"></textarea>

                    <span class="ai-listening" x-show="listening" x-cloak>{{ __('Mendengarkan…') }}</span>
                    <button type="submit" class="ai-send" :disabled="busy || (!cleanText() && !attachments.length)" aria-label="{{ __('Kirim pesan') }}" :title="busy ? '{{ __('Mengirim…') }}' : '{{ __('Kirim pesan') }}'">
                        <svg x-show="!busy" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M3.4 20.6 21.5 12 3.4 3.4 3.4 10l12.9 2L3.4 14z" />
                        </svg>
                        <svg x-show="busy" class="ai-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" />
                            <path fill="currentColor" d="M12 3a9 9 0 018.66 6.56l-2.9.78A6 6 0 0012 6V3z" />
                        </svg>
                    </button>
                </form>
                <p class="ai-composer-hint">{{ __('AI dapat membuat kesalahan. Verifikasi informasi penting dari sistem.') }}</p>
            </div>
        </section>
    </div>

    <style>
        .main-content{height:100dvh;overflow:hidden}
        .main-content main{display:flex;flex-direction:column;min-height:0;overflow:hidden;padding:0!important}
        .ai-page{position:relative;display:flex;flex:1;min-height:0;width:100%;overflow:hidden;background:var(--card-bg);color:var(--text-primary)}
        .ai-history{position:relative;z-index:40;display:flex;width:17rem;flex:none;flex-direction:column;border-right:1px solid var(--card-border);background:var(--card-bg-hover);transition:transform .25s var(--ease-out)}
        .ai-history-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:1.1rem 1rem .9rem}
        .ai-history-brand{display:flex;align-items:center;gap:.6rem;font-size:.85rem;font-weight:700;letter-spacing:-.01em}
        .ai-history-mark,.ai-chat-icon,.ai-message-avatar{display:inline-flex;align-items:center;justify-content:center;flex:none;color:#fff;background:rgb(var(--accent-600) / 1)}
        .ai-history-mark{width:1.8rem;height:1.8rem;border-radius:.55rem}
        .ai-icon-button{display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border:1px solid transparent;border-radius:.65rem;color:var(--text-secondary);background:transparent;cursor:pointer}
        .ai-icon-button svg{width:1.1rem;height:1.1rem}
        .ai-icon-button:hover{background:var(--card-bg);color:var(--text-primary);border-color:var(--card-border)}
        .ai-history-close,.ai-mobile-history{display:none}
        .ai-new-chat{display:flex;align-items:center;justify-content:center;gap:.55rem;margin:0 1rem .9rem;padding:.65rem .85rem;border:1px solid rgb(var(--accent-600) / 1);border-radius:.65rem;color:#fff;background:rgb(var(--accent-600) / 1);font-size:.83rem;font-weight:700;cursor:pointer}
        .ai-new-chat:hover{background:rgb(var(--accent-700) / 1);border-color:rgb(var(--accent-700) / 1)}
        .ai-new-chat svg{width:1rem;height:1rem}
        .ai-history-label{padding:.25rem 1rem .5rem;color:var(--text-muted);font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase}
        .ai-history-list{min-height:0;flex:1;overflow-y:auto;padding:0 .55rem}
        .ai-history-empty{padding:1.5rem .6rem;color:var(--text-muted);font-size:.78rem;line-height:1.5;text-align:center}
        .ai-history-item{position:relative;display:flex;align-items:center;margin:.15rem 0;border:1px solid transparent;border-radius:.65rem;color:var(--text-secondary)}
        .ai-history-item:hover,.ai-history-item.is-active{background:var(--card-bg);border-color:var(--card-border);color:var(--text-primary)}
        .ai-history-item.is-active{box-shadow:var(--shadow-rest)}
        .ai-history-link{display:flex;min-width:0;flex:1;flex-direction:column;gap:.2rem;padding:.62rem .4rem .62rem .65rem;border:0;background:transparent;color:inherit;text-align:left;cursor:pointer}
        .ai-history-title{overflow:hidden;font-size:.78rem;font-weight:600;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}
        .ai-history-date{color:var(--text-muted);font-size:.65rem}
        .ai-history-rename{display:flex;align-items:center;gap:.2rem;width:100%;padding:.35rem}
        .ai-history-rename input{min-width:0;flex:1;padding:.35rem .4rem!important;border:1px solid var(--input-border)!important;border-radius:.4rem!important;background:var(--input-bg)!important;font-size:.72rem}
        .ai-history-rename button{display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.8rem;border:0;border-radius:.35rem;color:var(--text-secondary);background:transparent;cursor:pointer}
        .ai-history-rename button:hover{color:rgb(var(--accent-600) / 1);background:var(--card-bg-hover)}
        .ai-history-more{display:inline-flex;align-items:center;justify-content:center;width:2.1rem;height:2.3rem;margin-right:.2rem;border:0;border-radius:.45rem;color:var(--text-muted);background:transparent;cursor:pointer;opacity:0}
        .ai-history-item:hover .ai-history-more,.ai-history-item.is-active .ai-history-more,.ai-history-more:focus-visible{opacity:1}
        .ai-history-more:hover{background:var(--card-bg-hover);color:var(--text-primary)}
        .ai-history-more svg{width:1rem;height:1rem}
        .ai-history-menu{position:absolute;top:2.4rem;right:.35rem;z-index:60;width:8.5rem;padding:.3rem;border:1px solid var(--card-border);border-radius:.65rem;background:var(--card-bg);box-shadow:var(--shadow-lift)}
        .ai-history-menu button{display:flex;align-items:center;gap:.45rem;width:100%;padding:.48rem .55rem;border:0;border-radius:.4rem;color:var(--text-secondary);background:transparent;font-size:.75rem;text-align:left;cursor:pointer}
        .ai-history-menu button:hover{background:var(--card-bg-hover);color:var(--text-primary)}
        .ai-history-menu button.is-danger:hover{color:#dc2626;background:rgba(254,226,226,.65)}
        .ai-history-menu svg{width:.85rem;height:.85rem}
        .ai-history-foot{display:flex;align-items:center;gap:.45rem;padding:.8rem 1rem 1rem;border-top:1px solid var(--card-border);color:var(--text-muted);font-size:.68rem}
        .ai-status-dot{display:inline-block;width:.42rem;height:.42rem;flex:none;border-radius:9999px;background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.12)}
        .ai-history-backdrop{display:none}
        .ai-workspace{position:relative;display:flex;min-width:0;min-height:0;flex:1;flex-direction:column;background:var(--card-bg)}
        .ai-chat-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:4.25rem;padding:.75rem clamp(1rem,3vw,2.5rem);border-bottom:1px solid var(--card-border);background:var(--card-bg)}
        .ai-chat-heading{display:flex;align-items:center;gap:.7rem;min-width:0}
        .ai-chat-icon{width:2.2rem;height:2.2rem;border-radius:.7rem}
        .ai-chat-heading h1{overflow:hidden;margin:0;font-size:.94rem;font-weight:700;line-height:1.25;text-overflow:ellipsis;white-space:nowrap}
        .ai-chat-heading p{display:flex;align-items:center;gap:.4rem;margin-top:.2rem;color:var(--text-muted);font-size:.7rem}
        .ai-header-new{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem .7rem;border:1px solid var(--card-border);border-radius:.6rem;color:var(--text-secondary);background:var(--card-bg);font-size:.75rem;font-weight:600;cursor:pointer}
        .ai-header-new:hover{border-color:rgb(var(--accent-300) / 1);color:rgb(var(--accent-700) / 1);background:rgb(var(--accent-50) / 1)}
        .dark .ai-header-new:hover{color:rgb(var(--accent-200) / 1);background:rgb(var(--accent-600) / .15)}
        .ai-header-new svg{width:1rem;height:1rem}
        .ai-conversation{display:flex;min-height:0;flex:1;flex-direction:column;gap:.75rem;overflow-y:auto;overflow-x:hidden;padding:clamp(.75rem,2vw,1.25rem) clamp(1rem,3vw,2.5rem) 1rem}
        .ai-message{width:100%;max-width:80rem;margin:0 auto}
        .ai-assistant-row{display:flex;align-items:flex-start;gap:.9rem}
        .ai-message-avatar{width:2.4rem;height:2.4rem;margin-top:0;border-radius:.7rem}
        .ai-message-body{min-width:0;flex:0 1 auto;max-width:100%}
        .ai-message-author{margin-bottom:.35rem;color:var(--text-secondary);font-size:.73rem;font-weight:700}
        .ai-message-body .md{color:var(--text-primary);font-size:.9rem;line-height:1.65;overflow-wrap:anywhere}
        .ai-user-row{display:flex;justify-content:flex-end;align-items:flex-start;gap:.75rem;min-height:0}
        .ai-user-text{align-self:flex-start;height:auto;min-height:0;max-width:min(80%,48rem);padding-top:.2rem;color:var(--text-primary);font-size:.9rem;line-height:1.55;text-align:right;white-space:normal;overflow-wrap:anywhere}
        .ai-user-msg{display:inline-block;max-width:100%;white-space:pre-wrap;padding:.45rem .85rem;border-radius:1rem 1rem .25rem 1rem;background:rgb(var(--accent-100) / 1);border:1px solid rgb(var(--accent-300) / .5)}
        .dark .ai-user-msg{background:rgb(var(--accent-500) / .3);border-color:rgb(var(--accent-400) / .35);color:rgb(var(--accent-100) / 1)}
        .ai-message-body:has(.md){padding:.7rem .9rem;border-radius:.25rem 1rem 1rem 1rem;background:rgb(148 163 184 / .16);border:1px solid rgb(148 163 184 / .3)}
        .dark .ai-message-body:has(.md){background:rgb(255 255 255 / .08);border-color:rgb(255 255 255 / .14)}
        .ai-user-avatar{display:inline-flex;align-items:center;justify-content:center;width:2.4rem;height:2.4rem;flex:none;overflow:hidden;border-radius:.7rem;color:#fff;background:rgb(var(--accent-600) / 1);font-size:.95rem;font-weight:700}
        .dark .ai-user-avatar{background:rgb(var(--accent-500) / .85)}
        .ai-user-avatar img{width:100%;height:100%;object-fit:cover}
        .ai-sources,.ai-message-files{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.65rem}
        .ai-source-chip,.ai-file-chip{display:inline-flex;align-items:center;gap:.35rem;max-width:100%;padding:.25rem .5rem;border:1px solid var(--card-border);border-radius:.45rem;color:var(--text-secondary);background:var(--card-bg-hover);font-size:.7rem}
        .ai-source-chip svg,.ai-file-chip svg{width:.8rem;height:.8rem;flex:none}
        .ai-file-chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ai-empty{display:flex;align-items:center;justify-content:center;min-height:100%;flex:1;flex-direction:column;padding:3rem 1rem;text-align:center}
        .ai-empty-mark{display:inline-flex;align-items:center;justify-content:center;width:3.1rem;height:3.1rem;margin-bottom:1rem;border:1px solid rgb(var(--accent-200) / 1);border-radius:1rem;color:rgb(var(--accent-600) / 1);background:rgb(var(--accent-50) / 1)}
        .dark .ai-empty-mark{border-color:rgb(var(--accent-400) / .3);background:rgb(var(--accent-600) / .15);color:rgb(var(--accent-300) / 1)}
        .ai-empty h2{font-size:1.1rem;font-weight:700}
        .ai-empty p{max-width:28rem;margin:.45rem 0 1.2rem;color:var(--text-secondary);font-size:.82rem;line-height:1.55}
        .ai-suggestions{display:flex;flex-wrap:wrap;justify-content:center;gap:.5rem}
        .ai-suggestions button{padding:.5rem .7rem;border:1px solid var(--card-border);border-radius:.6rem;color:var(--text-secondary);background:var(--card-bg);font-size:.72rem;cursor:pointer}
        .ai-suggestions button:hover{border-color:rgb(var(--accent-300) / 1);color:rgb(var(--accent-700) / 1);background:rgb(var(--accent-50) / 1)}
        .dark .ai-suggestions button:hover{color:rgb(var(--accent-200) / 1);background:rgb(var(--accent-600) / .15)}
        .ai-thinking{max-width:80rem;margin:0 auto;width:100%}
        .ai-thinking-body{display:flex;align-items:center;gap:.28rem;height:1.8rem;padding:0 .75rem;border:1px solid var(--card-border);border-radius:.65rem;background:var(--card-bg-hover)}
        .ai-thinking-body span,.ai-loading-history span{width:.35rem;height:.35rem;border-radius:9999px;background:var(--text-muted);animation:ai-pulse 1.15s infinite ease-in-out}
        .ai-thinking-body span:nth-child(2),.ai-loading-history span:nth-child(2){animation-delay:.15s}
        .ai-thinking-body span:nth-child(3),.ai-loading-history span:nth-child(3){animation-delay:.3s}
        .ai-loading-history{display:flex;align-items:center;justify-content:center;gap:.3rem;padding:1rem;color:var(--text-muted);font-size:.75rem}
        .ai-loading-history span{animation-delay:0s}
        @keyframes ai-pulse{0%,60%,100%{transform:translateY(0);opacity:.45}30%{transform:translateY(-3px);opacity:1}}
        .ai-jump{position:absolute;right:1.5rem;bottom:8rem;display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border:1px solid var(--card-border);border-radius:9999px;color:var(--text-secondary);background:var(--card-bg);box-shadow:var(--shadow-lift);cursor:pointer}
        .ai-jump:hover{color:var(--text-primary);background:var(--card-bg-hover)}
        .ai-jump svg{width:1rem;height:1rem}
        .ai-composer-area{width:100%;max-width:80rem;margin:0 auto;padding:.5rem clamp(1rem,3vw,2.5rem) max(.8rem, env(safe-area-inset-bottom))}
        .ai-error{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.55rem;padding:.55rem .75rem;border:1px solid rgba(248,113,113,.4);border-radius:.6rem;color:#b91c1c;background:rgba(254,226,226,.85);font-size:.78rem}
        .dark .ai-error{color:#fecaca;background:rgba(127,29,29,.45)}
        .ai-error button{border:0;color:inherit;background:transparent;font-size:1.1rem;line-height:1;cursor:pointer}
        .ai-attachment-preview{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.55rem}
        .ai-attachment-card{position:relative;display:flex;align-items:center;gap:.5rem;min-width:10rem;max-width:14rem;padding:.4rem .5rem;border:1px solid var(--card-border);border-radius:.65rem;background:var(--card-bg-hover)}
        .ai-attachment-card img,.ai-attachment-file-icon{display:inline-flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;flex:none;border-radius:.4rem;object-fit:cover;background:var(--card-bg)}
        .ai-attachment-file-icon{color:rgb(var(--accent-600) / 1)}
        .ai-attachment-file-icon svg{width:1.15rem;height:1.15rem}
        .ai-attachment-meta{display:flex;min-width:0;flex:1;flex-direction:column;gap:.1rem}
        .ai-attachment-meta strong{overflow:hidden;font-size:.7rem;text-overflow:ellipsis;white-space:nowrap}
        .ai-attachment-meta small{color:var(--text-muted);font-size:.62rem}
        .ai-attachment-card button{display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border:0;border-radius:9999px;color:var(--text-muted);background:transparent;font-size:1rem;cursor:pointer}
        .ai-attachment-card button:hover{color:#dc2626;background:rgba(254,226,226,.7)}
        .ai-composer{position:relative;display:flex;align-items:flex-end;gap:.45rem;padding:.45rem;border:1px solid var(--card-border);border-radius:1rem;background:var(--card-bg);box-shadow:var(--shadow-lift)}
        .ai-composer:focus-within{border-color:rgb(var(--accent-500) / .7);box-shadow:var(--shadow-lift),0 0 0 3px rgb(var(--accent-500) / .12)}
        .ai-plus-wrap{position:relative;flex:none}
        .ai-composer-button{display:inline-flex;align-items:center;justify-content:center;width:2.7rem;height:2.7rem;border:0;border-radius:.7rem;color:var(--text-secondary);background:transparent;cursor:pointer}
        .ai-composer-button:hover{color:rgb(var(--accent-600) / 1);background:rgb(var(--accent-50) / 1)}
        .dark .ai-composer-button:hover{color:rgb(var(--accent-200) / 1);background:rgb(var(--accent-600) / .15)}
        .ai-composer-button svg{width:1.2rem;height:1.2rem}
        .ai-plus-menu{position:absolute;bottom:3.15rem;left:0;z-index:70;width:14rem;padding:.35rem;border:1px solid var(--card-border);border-radius:.75rem;background:var(--card-bg);box-shadow:var(--shadow-lift)}
        .ai-plus-menu button{display:flex;align-items:center;gap:.65rem;width:100%;padding:.55rem .5rem;border:0;border-radius:.5rem;color:var(--text-secondary);background:transparent;font-size:.76rem;text-align:left;cursor:pointer}
        .ai-plus-menu button:hover,.ai-plus-menu button.is-listening{color:rgb(var(--accent-700) / 1);background:rgb(var(--accent-50) / 1)}
        .dark .ai-plus-menu button:hover,.dark .ai-plus-menu button.is-listening{color:rgb(var(--accent-200) / 1);background:rgb(var(--accent-600) / .15)}
        .ai-menu-icon{display:inline-flex;align-items:center;justify-content:center;width:1.8rem;height:1.8rem;border-radius:.5rem}
        .ai-menu-icon svg{width:1rem;height:1rem}
        .ai-menu-icon.is-image{color:rgb(var(--accent-600) / 1);background:rgb(var(--accent-50) / 1)}.ai-menu-icon.is-file{color:rgb(var(--accent-700) / 1);background:rgb(var(--accent-100) / 1)}.ai-menu-icon.is-mic{color:rgb(var(--accent-700) / 1);background:rgb(var(--accent-100) / 1)}
        .dark .ai-menu-icon.is-image{color:rgb(var(--accent-200) / 1);background:rgb(var(--accent-600) / .2)}.dark .ai-menu-icon.is-file{color:rgb(var(--accent-200) / 1);background:rgb(var(--accent-600) / .2)}.dark .ai-menu-icon.is-mic{color:rgb(var(--accent-200) / 1);background:rgb(var(--accent-600) / .2)}
        .ai-composer textarea{min-height:2.7rem;max-height:10rem;flex:1;resize:none;padding:.62rem .35rem;border:0!important;outline:0!important;background:transparent!important;box-shadow:none!important;color:var(--input-text)!important;font-size:.88rem;line-height:1.45;overflow-y:auto}
        .ai-composer textarea::placeholder{color:var(--text-muted)}
        .ai-send{display:inline-flex;align-items:center;justify-content:center;width:2.7rem;height:2.7rem;flex:none;border:0;border-radius:.7rem;color:#fff;background:rgb(var(--accent-600) / 1);cursor:pointer}
        .ai-send:hover:not(:disabled){background:rgb(var(--accent-700) / 1)}
        .ai-send:disabled{cursor:not-allowed;opacity:.45}
        .ai-send svg{width:1.15rem;height:1.15rem}
        .ai-spin{animation:ai-spin 1s linear infinite}@keyframes ai-spin{to{transform:rotate(360deg)}}
        .ai-listening{position:absolute;right:4.25rem;bottom:.95rem;color:#dc2626;font-size:.68rem;font-weight:600}
        .ai-composer-hint{margin:.45rem 0 0;color:var(--text-muted);font-size:.64rem;text-align:center}
        .md>:first-child{margin-top:0}.md>:last-child{margin-bottom:0}.md p{margin:.5rem 0}.md ul,.md ol{display:flex;flex-direction:column;gap:.25rem;margin:.5rem 0;padding-left:1.4rem}.md ul{list-style:disc}.md ol{list-style:decimal}.md li>ul,.md li>ol{margin:.25rem 0}.md h1,.md h2,.md h3,.md h4{margin:1rem 0 .5rem;font-weight:700;line-height:1.3}.md h1{font-size:1.25rem}.md h2{font-size:1.125rem}.md h3{font-size:1rem}.md a{color:rgb(var(--accent-600) / 1);text-decoration:underline;text-underline-offset:2px}.dark .md a{color:rgb(var(--accent-300) / 1)}.md strong{font-weight:700}.md code{padding:.15rem .35rem;border-radius:.35rem;background:var(--card-bg-hover);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.875em}.md pre{max-width:100%;margin:.75rem 0;padding:.75rem 1rem;overflow-x:auto;border:1px solid var(--card-border);border-radius:.65rem;background:var(--card-bg-hover)}.md pre code{padding:0;background:transparent}.md blockquote{margin:.75rem 0;padding-left:1rem;border-left:3px solid var(--card-border);color:var(--text-secondary)}.md table{display:block;width:fit-content;max-width:100%;margin:.75rem 0;overflow-x:auto;border-collapse:collapse;font-size:.875rem;white-space:nowrap}.md th,.md td{padding:.45rem .75rem;border:1px solid var(--card-border);text-align:left}.md th{background:var(--card-bg-hover);font-weight:600}.md hr{margin:1rem 0;border:0;border-top:1px solid var(--card-border)}
        @media(max-width:767px){
            .ai-history{position:absolute;inset:0 auto 0 0;width:min(17rem,86vw);transform:translateX(-102%);box-shadow:var(--shadow-lift)}
            .ai-history.is-open{transform:translateX(0)}
            .ai-history-close,.ai-mobile-history{display:inline-flex}
            .ai-history-backdrop{position:absolute;inset:0;z-index:30;background:rgba(15,23,42,.42)}
            .ai-header-new{padding:.5rem}.ai-header-new span{display:none}
            .ai-conversation{padding:1rem .8rem}.ai-composer-area{padding-left:.8rem;padding-right:.8rem}.ai-user-text{max-width:88%}
            .ai-jump{right:.8rem;bottom:8rem}
        }
        @media(prefers-reduced-motion:reduce){.ai-thinking-body span,.ai-loading-history span{animation:none;opacity:.6}.ai-history,.ai-spin{transition:none;animation:none}}
    </style>

    <script>
        function officeAssistant() {
            return {
                conversationId: window.chatInit?.conversationId ?? null,
                conversations: window.chatInit?.conversations ?? [],
                messages: window.chatInit?.messages ?? [],
                userName: window.chatInit?.user?.name ?? '',
                userInitial: window.chatInit?.user?.initial ?? '?',
                userPhoto: window.chatInit?.user?.photo ?? null,
                draft: '',
                attachments: [],
                busy: false,
                loadingConversation: false,
                error: '',
                atBottom: true,
                historyOpen: false,
                plusOpen: false,
                menuId: null,
                editingId: null,
                renameDraft: '',
                listening: false,
                speechSupported: false,
                recognition: null,
                speechBase: '',
                showUrl: @js(route('ai.assistant.conversations.show', ['conversation' => '__CONVERSATION__'])),
                renameUrl: @js(route('ai.assistant.conversations.rename', ['conversation' => '__CONVERSATION__'])),
                deleteUrl: @js(route('ai.assistant.conversations.destroy', ['conversation' => '__CONVERSATION__'])),
                init() {
                    this.speechSupported = 'SpeechRecognition' in window || 'webkitSpeechRecognition' in window;
                    this.scrollToBottom(false);
                    this.$nextTick(() => {
                        if (window.matchMedia('(min-width: 768px)').matches) this.focusInput();
                    });
                },
                headers(json = false) {
                    const headers = {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    };
                    if (json) headers['Content-Type'] = 'application/json';
                    return headers;
                },
                onScroll() {
                    const el = this.$refs.messages;
                    this.atBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < 120;
                },
                resize() {
                    const el = this.$refs.input;
                    if (!el) return;
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
                },
                focusInput() {
                    this.$nextTick(() => this.$refs.input?.focus());
                },
                onEnter(event) {
                    if (event.isComposing) return;
                    if (event.shiftKey) {
                        this.$nextTick(() => this.resize());
                        return;
                    }
                    event.preventDefault();
                    this.send();
                },
                // Samakan dengan backend cleanMessage(): buang karakter
                // tak terlihat (nol-lebar, kontrol) + pangkas spasi unicode
                // di kedua ujung agar bubble optimistik tidak menggelembung.
                cleanText() {
                    return (this.draft || '')
                        .replace(/[\u200B-\u200D\u2060\uFEFF\u00AD\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/gu, '')
                        .replace(/^[\s\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000]+|[\s\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000]+$/gu, '')
                        .trim();
                },
                renderMarkdown(content) {
                    try {
                        return window.DOMPurify.sanitize(window.marked.parse(content ?? '', { breaks: true }));
                    } catch (error) {
                        const element = document.createElement('span');
                        element.textContent = content ?? '';
                        return element.innerHTML;
                    }
                },
                scrollToBottom(smooth) {
                    this.$nextTick(() => {
                        const el = this.$refs.messages;
                        el.scrollTo({ top: el.scrollHeight, behavior: smooth && !this.reducedMotion() ? 'smooth' : 'auto' });
                    });
                },
                reducedMotion() {
                    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                },
                setUrl(id = null) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('new');
                    if (id) url.searchParams.set('conversation_id', id);
                    else url.searchParams.set('new', '1');
                    window.history.replaceState({}, '', url);
                },
                newChat() {
                    if (this.busy) return;
                    this.stopSpeech();
                    this.revokeAttachments(this.attachments);
                    this.conversationId = null;
                    this.messages = [];
                    this.draft = '';
                    this.attachments = [];
                    this.error = '';
                    this.plusOpen = false;
                    this.menuId = null;
                    this.editingId = null;
                    this.historyOpen = false;
                    this.setUrl();
                    this.scrollToBottom(false);
                    this.focusInput();
                },
                async openConversation(id) {
                    this.menuId = null;
                    this.editingId = null;
                    if (this.busy || this.loadingConversation) return;
                    if (this.conversationId === id) {
                        this.historyOpen = false;
                        return;
                    }
                    this.loadingConversation = true;
                    this.error = '';
                    try {
                        const response = await fetch(this.showUrl.replace('__CONVERSATION__', encodeURIComponent(id)), { headers: this.headers() });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || '{{ __('Percakapan tidak dapat dimuat.') }}');
                        this.conversationId = data.conversation.id;
                        this.messages = data.messages ?? [];
                        this.setUrl(this.conversationId);
                        this.historyOpen = false;
                        this.scrollToBottom(false);
                    } catch (error) {
                        this.error = error.message || '{{ __('Percakapan tidak dapat dimuat.') }}';
                    } finally {
                        this.loadingConversation = false;
                    }
                },
                beginRename(chat) {
                    this.menuId = null;
                    this.editingId = chat.id;
                    this.renameDraft = chat.title;
                },
                cancelRename() {
                    this.editingId = null;
                    this.renameDraft = '';
                },
                async saveRename(chat) {
                    const title = this.renameDraft.trim();
                    if (!title) return;
                    try {
                        const response = await fetch(this.renameUrl.replace('__CONVERSATION__', encodeURIComponent(chat.id)), {
                            method: 'PATCH',
                            headers: this.headers(true),
                            body: JSON.stringify({ title }),
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || '{{ __('Nama chat tidak dapat diubah.') }}');
                        this.conversations = this.conversations.map(item => item.id === chat.id ? data.conversation : item);
                        this.cancelRename();
                    } catch (error) {
                        this.error = error.message || '{{ __('Nama chat tidak dapat diubah.') }}';
                    }
                },
                async deleteConversation(chat) {
                    if (this.busy) return;
                    this.menuId = null;
                    if (!window.confirm(`{{ __('Hapus percakapan') }} "${chat.title}"?`)) return;
                    try {
                        const response = await fetch(this.deleteUrl.replace('__CONVERSATION__', encodeURIComponent(chat.id)), {
                            method: 'DELETE',
                            headers: this.headers(),
                        });
                        const data = await response.json();
                        if (!response.ok || !data.deleted) throw new Error(data.message || '{{ __('Percakapan tidak dapat dihapus.') }}');
                        this.conversations = this.conversations.filter(item => item.id !== chat.id);
                        if (this.conversationId === chat.id) this.newChat();
                    } catch (error) {
                        this.error = error.message || '{{ __('Percakapan tidak dapat dihapus.') }}';
                    }
                },
                upsertConversation(conversation) {
                    this.conversations = [conversation, ...this.conversations.filter(item => item.id !== conversation.id)];
                },
                addFiles(event) {
                    const files = Array.from(event.target.files ?? []);
                    const available = Math.max(0, 3 - this.attachments.length);
                    const exceeded = files.length > available;
                    if (exceeded) this.error = '{{ __('Maksimal 3 lampiran per pesan.') }}';
                    files.slice(0, available).forEach(file => {
                        this.attachments.push({
                            id: `${file.name}-${file.lastModified}-${Math.random()}`,
                            file,
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            isImage: file.type.startsWith('image/'),
                            previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                        });
                    });
                    event.target.value = '';
                    if (!exceeded) this.error = '';
                },
                removeAttachment(index) {
                    const [attachment] = this.attachments.splice(index, 1);
                    this.revokeAttachments([attachment]);
                },
                revokeAttachments(attachments) {
                    attachments.forEach(attachment => {
                        if (attachment?.previewUrl) URL.revokeObjectURL(attachment.previewUrl);
                    });
                },
                formatBytes(bytes) {
                    if (!bytes) return '0 B';
                    const units = ['B', 'KB', 'MB', 'GB'];
                    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
                    return `${(bytes / Math.pow(1024, index)).toFixed(index ? 1 : 0)} ${units[index]}`;
                },
                formatDate(value) {
                    if (!value) return '';
                    return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' }).format(new Date(value));
                },
                toggleSpeech() {
                    if (this.listening) {
                        this.stopSpeech();
                        return;
                    }
                    if (!this.speechSupported) {
                        this.error = '{{ __('Input suara tidak didukung browser ini. Gunakan Chrome atau Edge terbaru.') }}';
                        return;
                    }
                    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                    this.recognition = new Recognition();
                    this.recognition.lang = 'id-ID';
                    this.recognition.interimResults = true;
                    this.recognition.continuous = false;
                    this.speechBase = this.draft.trim();
                    this.recognition.onresult = event => {
                        const transcript = Array.from(event.results).map(result => result[0].transcript).join('');
                        this.draft = [this.speechBase, transcript.trim()].filter(Boolean).join(' ');
                        this.$nextTick(() => this.resize());
                    };
                    this.recognition.onerror = event => {
                        if (event.error !== 'aborted') this.error = '{{ __('Input suara gagal. Silakan coba lagi.') }}';
                        this.listening = false;
                    };
                    this.recognition.onend = () => {
                        this.listening = false;
                        this.focusInput();
                    };
                    this.error = '';
                    this.recognition.start();
                    this.listening = true;
                },
                stopSpeech() {
                    this.recognition?.stop();
                    this.listening = false;
                },
                async send() {
                    const text = this.cleanText();
                    if ((!text && !this.attachments.length) || this.busy) return;

                    const pendingAttachments = this.attachments.map(attachment => ({ ...attachment }));
                    const visibleAttachments = pendingAttachments.map(({ file, ...attachment }) => attachment);
                    const tempId = `temp-${Date.now()}`;
                    this.error = '';
                    this.messages.push({
                        id: tempId,
                        role: 'user',
                        content: text || '{{ __('Lampiran dikirim') }}',
                        attachments: visibleAttachments,
                    });
                    this.draft = '';
                    this.attachments = [];
                    this.busy = true;
                    this.$nextTick(() => this.resize());
                    this.scrollToBottom(true);

                    const payload = new FormData();
                    payload.append('message', text);
                    if (this.conversationId) payload.append('conversation_id', this.conversationId);
                    pendingAttachments.forEach(attachment => payload.append('attachments[]', attachment.file));

                    try {
                        const response = await fetch('{{ route('ai.assistant.send') }}', {
                            method: 'POST',
                            headers: this.headers(),
                            body: payload,
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || '{{ __('Terjadi kesalahan saat menghubungi AI.') }}');
                        this.conversationId = data.conversation_id;
                        this.upsertConversation(data.conversation);
                        this.setUrl(this.conversationId);
                        // Bubble optimistik tampil dari draft mentah; ganti ke
                        // teks bersih yang benar-benar dikirim (antispasi aneh).
                        const mine = this.messages.find(message => message.id === tempId);
                        if (mine) mine.content = text;
                        this.messages.push({ id: `${data.conversation_id}-${Date.now()}`, role: 'assistant', content: data.message, sources: data.sources ?? [], attachments: [] });
                        this.scrollToBottom(true);
                    } catch (error) {
                        this.messages = this.messages.filter(message => message.id !== tempId);
                        this.draft = text;
                        this.attachments = pendingAttachments;
                        this.error = error.message || '{{ __('Gagal terhubung ke server. Silakan coba lagi.') }}';
                    } finally {
                        this.busy = false;
                        this.focusInput();
                    }
                },
                destroy() {
                    this.stopSpeech();
                    this.revokeAttachments(this.attachments);
                },
            };
        }
    </script>
</x-app-layout>
