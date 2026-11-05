import React from 'react';
import { cn } from '../../lib/utils';
import { motion, HTMLMotionProps } from 'framer-motion';

interface BentoCardProps extends HTMLMotionProps<"div"> {
  title: string;
  description: string;
  icon?: React.ReactNode;
  className?: string;
  children?: React.ReactNode;
  gradient?: string;
}

export function BentoCard({ title, description, icon, className, children, gradient, ...props }: BentoCardProps) {
  return (
    <motion.div
      whileHover={{ y: -5 }}
      className={cn(
        'relative overflow-hidden rounded-3xl border border-slate-200/60 bg-white p-8 shadow-sm transition-all hover:shadow-xl',
        className
      )}
      {...props}
    >
      {/* Subtle Background Gradient */}
      {gradient && (
        <div className={cn("absolute -inset-px opacity-10 transition duration-500 group-hover:opacity-20", gradient)} />
      )}
      
      <div className="relative z-10 flex h-full flex-col">
        {icon && (
          <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 text-slate-900 shadow-sm ring-1 ring-slate-200/50">
            {icon}
          </div>
        )}
        <h3 className="mb-3 text-2xl font-bold text-slate-900 tracking-tight">{title}</h3>
        <p className="text-slate-600 leading-relaxed flex-1">{description}</p>
        
        {children && <div className="mt-6">{children}</div>}
      </div>
    </motion.div>
  );
}
