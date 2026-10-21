<style>
    .sidebar-group { margin: 3px 0; }
    .sidebar-toggle { cursor: pointer; list-style: none; user-select: none; }
    .sidebar-toggle::-webkit-details-marker { display: none; }
    .sidebar-toggle::marker { content: ''; }
    .sidebar-toggle.has-active-child { color: #d7fff0; background: rgb(105 210 181 / 8%); }
    .sidebar-toggle:focus-visible, .sidebar-submenu a:focus-visible { outline: 2px solid #69d2b5; outline-offset: -2px; }
    .sidebar-chevron { flex: 0 0 13px; width: 13px; height: 13px; transition: transform 150ms ease; }
    .sidebar-group[open] > .sidebar-toggle .sidebar-chevron { transform: rotate(90deg); }
    .sidebar-submenu { margin: 4px 8px 8px 20px; padding-left: 9px; border-left: 1px solid #35535d; }
    .sidebar-submenu .sidebar-link { min-height: 32px; padding: 7px 9px; font-size: 10.5px; }
    .sidebar-submenu .sidebar-link > svg { width: 14px; height: 14px; }
    @media (prefers-reduced-motion: reduce) { .sidebar-chevron { transition: none; } }
</style>
