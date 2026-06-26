import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { MessagesSquare } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { useConversations, useMarkRead } from '../chat.hooks';
import { ConversationList } from '../components/ConversationList';
import { MessageThread } from '../components/MessageThread';
import { MessageComposer } from '../components/MessageComposer';
import { NewConversationModal } from '../components/NewConversationModal';

export default function ChatPage() {
  const { t } = useTranslation('chat');
  const { user } = useAuth();
  const convQ = useConversations();
  const markRead = useMarkRead();

  const [activeId, setActiveId] = useState<number | null>(null);
  const [modalOpen, setModalOpen] = useState(false);

  const conversations = convQ.data ?? [];
  const activeConversation = conversations.find((c) => c.id === activeId) ?? null;
  // صورة المرسِل تظهر في المحادثات الجماعية (عامة/مجموعة) لتمييز الأطراف، لا في الخاص.
  const showSenderAvatar = activeConversation?.type !== 'direct';

  // اختيار افتراضي: أول محادثة عند التحميل.
  useEffect(() => {
    if (activeId === null && conversations.length > 0) {
      setActiveId(conversations[0].id);
    }
  }, [conversations, activeId]);

  const select = (id: number) => {
    setActiveId(id);
    markRead.mutate(id);
  };

  return (
    <div className="grid h-[calc(100vh-8rem)] grid-cols-1 overflow-hidden bg-background md:grid-cols-[320px_1fr]">
      {/* قائمة المحادثات */}
      <div className="hidden md:block">
        <ConversationList
          conversations={conversations}
          activeId={activeId}
          currentUserId={user?.id ?? null}
          onSelect={select}
          onNew={() => setModalOpen(true)}
          loading={convQ.isLoading}
        />
      </div>

      {/* الخيط + composer */}
      <div className="flex h-full flex-col">
        {activeId !== null ? (
          <>
            <MessageThread conversationId={activeId} showSenderAvatar={showSenderAvatar} />
            <MessageComposer conversationId={activeId} />
          </>
        ) : (
          <div className="flex flex-1 flex-col items-center justify-center gap-2 text-muted-foreground">
            <MessagesSquare className="h-10 w-10" />
            <p className="text-sm">{t('selectPrompt')}</p>
          </div>
        )}
      </div>

      <NewConversationModal open={modalOpen} onClose={() => setModalOpen(false)} onCreated={select} />
    </div>
  );
}
