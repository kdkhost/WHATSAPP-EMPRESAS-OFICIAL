import React from 'react';
import { cn } from '../../lib/utils';
import { motion, HTMLMotionProps } from 'framer-motion';

interface NeonButtonProps extends HTMLMotionProps<"button"> {
  children: React.ReactNode;
  className?: string;
  color?: 'blue' | 'purple' | 'emerald' | 'rose' | 'indigo';
  href?: string;
}

export function NeonButton({ children, className, color = 'blue', href, ...props }: NeonButtonProps) {
  const colorStyles = {
    blue: 'bg-blue-600 hover:bg-blue-500 shadow-blue-500/30 ring-blue-500/50',
    purple: 'bg-purple-600 hover:bg-purple-500 shadow-purple-500/30 ring-purple-500/50',
    emerald: 'bg-emerald-500 hover:bg-emerald-400 shadow-emerald-500/30 ring-emerald-500/50',
    rose: 'bg-rose-600 hover:bg-rose-500 shadow-rose-500/30 ring-rose-500/50',
    indigo: 'bg-indigo-600 hover:bg-indigo-500 shadow-indigo-500/30 ring-indigo-500/50',
  };

  const Component = href ? motion.a : motion.button;

  return (
    <Component
      href={href}
      whileHover={{ scale: 1.02 }}
      whileTap={{ scale: 0.98 }}
      className={cn(
        'relative inline-flex min-h-12 items-center justify-center whitespace-nowrap px-8 py-4 text-center text-sm font-semibold leading-none text-white transition-all rounded-full shadow-lg ring-1 hover:shadow-xl hover:ring-2',
        colorStyles[color],
        className
      )}
      {...(props as any)}
    >
      <span className="relative z-10 flex flex-nowrap items-center justify-center gap-2 whitespace-nowrap">{children}</span>
    </Component>
  );
}
