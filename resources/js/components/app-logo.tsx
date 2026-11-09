/**
 * Sidebar brand header: the real L-PRES logo with the project name and a
 * small "Admin portal" label, linking back to the dashboard.
 */
export default function AppLogo() {
    return (
        <>
            <span className="inline-block rounded-md bg-white p-1 shadow-sm ring-1 ring-black/5">
                <img src="/images/lpres/lpres-logo.jpeg" alt="Gombe State L-PRES" className="h-8 w-auto object-contain" />
            </span>
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-extrabold text-[#223645]">Gombe L-PRES</span>
                <span className="text-lpres-green text-[11px] font-bold tracking-[1px] uppercase">Admin portal</span>
            </div>
        </>
    );
}
