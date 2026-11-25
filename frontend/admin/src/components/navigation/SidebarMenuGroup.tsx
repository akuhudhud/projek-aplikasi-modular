interface SidebarMenuGroupProps {
  title: string;
  expanded: boolean;
  active: boolean;
  onToggle: () => void;
  children: React.ReactNode;
}

export default function SidebarMenuGroup({
  title,
  expanded,
  active,
  onToggle,
  children
}: SidebarMenuGroupProps) {
  return (
    <div className="admin-sidebar__group">
      <button
        type="button"
        className={[
          'admin-sidebar__item',
          'admin-sidebar__parent',
          active ? 'admin-sidebar__item--active' : ''
        ].join(' ')}
        aria-expanded={expanded}
        onClick={onToggle}
        style={{
          width: '100%',
          flex: 'initial',
          whiteSpace: 'normal'
        }}
      >
        <span>{title}</span>
        <span className="admin-sidebar__chevron">
          {expanded ? '−' : '+'}
        </span>
      </button>

      {expanded && (
        <div className="admin-sidebar__children">
          {children}
        </div>
      )}
    </div>
  );
}
