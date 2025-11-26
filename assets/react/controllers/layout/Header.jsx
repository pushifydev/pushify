import * as React from "react";
import * as NavigationMenu from "@radix-ui/react-navigation-menu";
import classNames from "classnames";
import { CaretDownIcon } from "@radix-ui/react-icons";

const Header = () => {
    return (
        <div className="mt-8">
            <div className="container mx-auto max-w-7xl px-4">
                <div className="w-full">
                    <NavigationMenu.Root className="relative z-10 w-full">
                        <NavigationMenu.List className=" backdrop-blur-md flex w-full max-w-none list-none px-10 items-center justify-between gap-2 rounded-full border border-white/10 bg-slate-900/80 p-2.5 shadow-[0_10px_35px_rgba(0,0,0,0.35)]">
                            <a
                                href="/"
                                className="text-2xl font-bold cursor-pointer"
                            >
                                Pushify
                            </a>
                            <div className="flex items-center gap-5">
                                <NavigationMenu.Item>
                                    <NavigationMenu.Trigger className="group inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2.5 font-semibold text-slate-200 transition-colors hover:bg-white/10 hover:text-white data-[state=open]:bg-indigo-600/20 data-[state=open]:text-white focus:outline-none focus:ring-2 focus:ring-indigo-400/60 focus:ring-offset-0">
                                        Features{" "}
                                        <CaretDownIcon
                                            className="h-4 w-4 transition-transform duration-200 group-data-[state=open]:-rotate-180"
                                            aria-hidden
                                        />
                                    </NavigationMenu.Trigger>
                                    <NavigationMenu.Content className="absolute left-0 right-0 top-full mt-2.5 flex w-full justify-center data-[state=open]:animate-fade-in data-[state=closed]:opacity-0 data-[state=closed]:pointer-events-none">
                                        <ul className="m-0 grid w-full max-w-6xl list-none grid-cols-3 gap-3 rounded-xl border border-white/10 bg-slate-950 p-4 shadow-[0_20px_45px_rgba(0,0,0,0.35)]">
                                            <li style={{ gridRow: "span 3" }}>
                                                <NavigationMenu.Link asChild>
                                                    <a
                                                        className="block h-full rounded-xl bg-primary p-4 text-white shadow-[0_14px_35px_rgba(0,0,0,0.35)] no-underline"
                                                        href="/"
                                                    >
                                                        <svg
                                                            aria-hidden
                                                            width="38"
                                                            height="38"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="white"
                                                            strokeWidth="2"
                                                        >
                                                            <path d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                        </svg>
                                                        <div className="mt-2 text-base font-bold">
                                                            Git Push to Deploy
                                                        </div>
                                                        <p className="mt-1.5 text-sm leading-relaxed text-white/85">
                                                            Push your code and
                                                            watch it go live
                                                            instantly. Automatic
                                                            builds and deployments.
                                                        </p>
                                                    </a>
                                                </NavigationMenu.Link>
                                            </li>

                                            <ListItem
                                                href="/features/databases"
                                                title="Managed Databases"
                                            >
                                                PostgreSQL, MySQL, MongoDB,
                                                Redis - deploy with one click.
                                                Automated backups included.
                                            </ListItem>
                                            <ListItem
                                                href="/features/backups"
                                                title="Automated Backups"
                                            >
                                                Scheduled and manual database backups.
                                                Point-in-time recovery and restoration.
                                            </ListItem>
                                            <ListItem
                                                href="/features/preview"
                                                title="Preview Deployments"
                                            >
                                                Auto-deploy every PR to unique URLs.
                                                Test before merging to production.
                                            </ListItem>
                                            <ListItem
                                                href="/features/ssl"
                                                title="Auto SSL Certificates"
                                            >
                                                Free SSL certificates with
                                                automatic renewal via Let's
                                                Encrypt.
                                            </ListItem>
                                            <ListItem
                                                href="/features/domains"
                                                title="Custom Domains"
                                            >
                                                Unlimited domains, automatic DNS,
                                                and buy domains directly from dashboard.
                                            </ListItem>
                                            <ListItem
                                                href="/features/monitoring"
                                                title="Monitoring & Alerts"
                                            >
                                                Real-time logs, uptime monitoring,
                                                and instant notifications.
                                            </ListItem>
                                            <ListItem
                                                href="/features/teams"
                                                title="Team Collaboration"
                                            >
                                                Invite team members, manage permissions,
                                                and collaborate on projects.
                                            </ListItem>
                                            <ListItem
                                                href="/features/self-hosted"
                                                title="Self-Hosted Option"
                                            >
                                                Deploy on your own servers.
                                                Open source and fully portable.
                                            </ListItem>
                                        </ul>
                                    </NavigationMenu.Content>
                                </NavigationMenu.Item>

                                <NavigationMenu.Item>
                                    <NavigationMenu.Trigger className="group inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2.5 font-semibold text-slate-200 transition-colors hover:bg-white/10 hover:text-white data-[state=open]:bg-indigo-600/20 data-[state=open]:text-white focus:outline-none focus:ring-2 focus:ring-indigo-400/60 focus:ring-offset-0">
                                        Resources{" "}
                                        <CaretDownIcon
                                            className="CaretDown"
                                            aria-hidden
                                        />
                                    </NavigationMenu.Trigger>
                                    <NavigationMenu.Content className="absolute left-0 top-full mt-2.5 flex w-full justify-center data-[state=open]:animate-fade-in data-[state=closed]:opacity-0 data-[state=closed]:pointer-events-none">
                                        <ul className="m-0 grid w-full max-w-6xl list-none grid-cols-3 gap-3 rounded-xl border border-white/10 bg-slate-950 p-4 shadow-[0_20px_45px_rgba(0,0,0,0.35)]">
                                            <ListItem
                                                title="Getting Started"
                                                href="/docs/getting-started"
                                            >
                                                Deploy your first application in
                                                under 5 minutes.
                                            </ListItem>
                                            <ListItem
                                                title="API Reference"
                                                href="/docs/api"
                                            >
                                                Complete API documentation for
                                                automation and integrations.
                                            </ListItem>
                                            <ListItem
                                                title="Server Setup"
                                                href="/docs/server-setup"
                                            >
                                                Configure your own servers with
                                                our simple installation script.
                                            </ListItem>
                                            <ListItem
                                                title="CLI Tool"
                                                href="/docs/cli"
                                            >
                                                Manage deployments from your
                                                terminal with the Pushify CLI.
                                            </ListItem>
                                            <ListItem
                                                title="GitHub Integration"
                                                href="/docs/github"
                                            >
                                                Connect repositories and enable
                                                automatic deployments.
                                            </ListItem>
                                            <ListItem
                                                title="Changelog"
                                                href="/changelog"
                                            >
                                                Latest updates, new features,
                                                and improvements.
                                            </ListItem>
                                        </ul>
                                    </NavigationMenu.Content>
                                </NavigationMenu.Item>

                                <NavigationMenu.Item>
                                    <NavigationMenu.Link
                                        className="px-3 font-semibold text-slate-200 transition-colors hover:text-white"
                                        href="/pricing"
                                    >
                                        Pricing
                                    </NavigationMenu.Link>
                                </NavigationMenu.Item>

                                <NavigationMenu.Item>
                                    <NavigationMenu.Link
                                        className="px-3 font-semibold text-slate-200 transition-colors hover:text-white"
                                        href="/docs"
                                    >
                                        Docs
                                    </NavigationMenu.Link>
                                </NavigationMenu.Item>

                                <NavigationMenu.Indicator className="z-10 flex h-2.5 items-end justify-center overflow-hidden transition-[width,transform] duration-200 data-[state=visible]:animate-fade-in data-[state=hidden]:opacity-0">
                                    <div className="relative top-3 h-3 w-3 rotate-45 rounded-sm border-l border-t border-white/10 bg-slate-900" />
                                </NavigationMenu.Indicator>
                            </div>
                            <a
                                href="/register"
                                className="bg-primary px-7 py-2 rounded-full font-semibold cursor-pointer hover:bg-primary/90 transition-colors"
                            >
                                Get Started
                            </a>
                        </NavigationMenu.List>

                        <div className="absolute left-0 top-[calc(100%+12px)] flex w-full justify-center">
                            <NavigationMenu.Viewport className="rounded-2xl border border-white/10 bg-slate-950 p-4 shadow-[0_22px_50px_rgba(0,0,0,0.35)] transition-[width,height,opacity,transform] duration-200 data-[state=open]:animate-pop data-[state=closed]:opacity-0 data-[state=closed]:scale-95" />
                        </div>
                    </NavigationMenu.Root>
                </div>
            </div>
        </div>
    );
};

const ListItem = React.forwardRef(
    ({ className, children, title, ...props }, forwardedRef) => (
        <li>
            <NavigationMenu.Link asChild>
                <a
                    className={classNames(
                        "block rounded-xl border border-white/10 bg-slate-900 px-3 py-3 text-slate-200 no-underline transition duration-150 hover:-translate-y-px hover:border-indigo-400/60 hover:text-white",
                        className
                    )}
                    {...props}
                    ref={forwardedRef}
                >
                    <div className="mb-1.5 font-semibold">{title}</div>
                    <p className="text-sm leading-snug text-slate-300">
                        {children}
                    </p>
                </a>
            </NavigationMenu.Link>
        </li>
    )
);

export default Header;
